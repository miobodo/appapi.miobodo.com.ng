<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PortfoliProjectController extends Controller
{

    // Add Portfolio Project
    public function addProfileInfo(Request $request){

        // Validate Request
        $validate = Validator::make($request->all(), [
            'title' => 'required|string|max:50',
            'role' => 'required|string|max:50',
            'description' => 'required|string|max:200',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();

            // Create the project
            $project = PortfolioProject::create([
                'user_id' => $user->id,
                'portfolio_id' => "",
                'title' => $request->title,
                'portfolio_bg' => "",
                'role' => $request->role,
                'project_description' => $request->description,
            ]);

            // Handle gallery images
            if ($request->hasFile('gallery')) {
                $galleryPaths = [];
                
                foreach ($request->file('gallery') as $image) {
                    try {
                        $imageInfo = getimagesize($image->getRealPath());
                        $mimeType = $imageInfo['mime'];

                        switch ($mimeType) {
                            case 'image/jpeg':
                                $img = imagecreatefromjpeg($image->getRealPath());
                                $extension = 'jpg';
                                break;
                            case 'image/png':
                                $img = imagecreatefrompng($image->getRealPath());
                                $extension = 'png';
                                break;
                            case 'image/gif':
                                $img = imagecreatefromgif($image->getRealPath());
                                $extension = 'gif';
                                break;
                            default:
                                continue 2; // Skip unsupported types
                        }

                        // Resize dimensions
                        $maxWidth = 800;
                        $maxHeight = 800;
                        list($width, $height) = $imageInfo;

                        $ratio = $width / $height;
                        if ($maxWidth / $maxHeight > $ratio) {
                            $newWidth = $maxHeight * $ratio;
                            $newHeight = $maxHeight;
                        } else {
                            $newWidth = $maxWidth;
                            $newHeight = $maxWidth / $ratio;
                        }

                        $newImage = imagecreatetruecolor($newWidth, $newHeight);

                        if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
                            imagealphablending($newImage, false);
                            imagesavealpha($newImage, true);
                            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                        }

                        imagecopyresampled($newImage, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                        $imageName = 'project_images/' . time() . '_' . uniqid() . '.' . $extension;
                        $fullPath = storage_path('app/public/' . $imageName);

                        // Create directory if it doesn't exist
                        if (!file_exists(dirname($fullPath))) {
                            mkdir(dirname($fullPath), 0755, true);
                        }

                        // Save based on type
                        switch ($mimeType) {
                            case 'image/jpeg':
                                imagejpeg($newImage, $fullPath, 75);
                                break;
                            case 'image/png':
                                imagepng($newImage, $fullPath, 6);
                                break;
                            case 'image/gif':
                                imagegif($newImage, $fullPath);
                                break;
                        }

                        imagedestroy($img);
                        imagedestroy($newImage);

                        $galleryPaths[] = Storage::url($imageName);
                    } catch (Exception $e) {
                        Log::error('Image processing failed: ' . $e->getMessage());
                        continue;
                    }
                }

                // Save gallery paths to project
                $project->project_images = json_encode($galleryPaths);
                $project->save();
            }

            DB::commit();

            // Refresh user data
            $user->refresh();

            return response()->json([
                'status' => 200,
                'message' => 'Project added successfully',
                'data' => [
                    'user' => $user,
                    'project' => $project
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred',
            ], 500);
        }
    }

    // Update Portfolio Project
    public function updatePortfolio(Request $request)
    {
        // Validate Request
        $validate = Validator::make($request->all(), [
            'portfolio_id' => 'required|exists:portfolio_project,portfolio_id',
            'title' => 'required|string|max:50',
            'role' => 'required|string|max:50',
            'description' => 'required|string|max:200',
            'gallery.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'existing_images' => 'nullable|json',
            'images_to_delete' => 'nullable|json',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $portfolioId = $request->portfolio_id;

            // Find the portfolio project
            $project = PortfolioProject::where('portfolio_id', $portfolioId)
                                    ->where('user_id', $user->id)
                                    ->first();

            if (!$project) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Bad request',
                ], 404);
            }

            // Update basic fields
            $project->title = $request->title;
            $project->role = $request->role;
            $project->project_description = $request->description;

            // Handle existing images
            $existingImages = [];
            if ($request->has('existing_images') && !empty($request->existing_images)) {
                $existingImages = json_decode($request->existing_images, true) ?: [];
            }

            // Handle images to delete
            $imagesToDelete = [];
            if ($request->has('images_to_delete') && !empty($request->images_to_delete)) {
                $imagesToDelete = json_decode($request->images_to_delete, true) ?: [];
                
                // Delete files from storage
                foreach ($imagesToDelete as $imageUrl) {
                    try {
                        // Extract the storage path from URL
                        if (strpos($imageUrl, '/storage/') !== false) {
                            $storagePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
                            if (Storage::disk('public')->exists($storagePath)) {
                                Storage::disk('public')->delete($storagePath);
                            }
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to delete image: ' . $imageUrl . ' - ' . $e->getMessage());
                    }
                }
            }

            // Handle new gallery images
            $newImagePaths = [];
            if ($request->hasFile('gallery')) {
                foreach ($request->file('gallery') as $image) {
                    try {
                        $imageInfo = getimagesize($image->getRealPath());
                        $mimeType = $imageInfo['mime'];

                        switch ($mimeType) {
                            case 'image/jpeg':
                                $img = imagecreatefromjpeg($image->getRealPath());
                                $extension = 'jpg';
                                break;
                            case 'image/png':
                                $img = imagecreatefrompng($image->getRealPath());
                                $extension = 'png';
                                break;
                            case 'image/gif':
                                $img = imagecreatefromgif($image->getRealPath());
                                $extension = 'gif';
                                break;
                            case 'image/webp':
                                $img = imagecreatefromwebp($image->getRealPath());
                                $extension = 'webp';
                                break;
                            default:
                                continue 2; // Skip unsupported types
                        }

                        // Resize dimensions
                        $maxWidth = 800;
                        $maxHeight = 800;
                        list($width, $height) = $imageInfo;

                        $ratio = $width / $height;
                        if ($maxWidth / $maxHeight > $ratio) {
                            $newWidth = $maxHeight * $ratio;
                            $newHeight = $maxHeight;
                        } else {
                            $newWidth = $maxWidth;
                            $newHeight = $maxWidth / $ratio;
                        }

                        $newImage = imagecreatetruecolor($newWidth, $newHeight);

                        if ($mimeType == 'image/png' || $mimeType == 'image/gif' || $mimeType == 'image/webp') {
                            imagealphablending($newImage, false);
                            imagesavealpha($newImage, true);
                            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
                        }

                        imagecopyresampled($newImage, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

                        $imageName = 'project_images/' . time() . '_' . uniqid() . '.' . $extension;
                        $fullPath = storage_path('app/public/' . $imageName);

                        // Create directory if it doesn't exist
                        if (!file_exists(dirname($fullPath))) {
                            mkdir(dirname($fullPath), 0755, true);
                        }

                        // Save based on type
                        switch ($mimeType) {
                            case 'image/jpeg':
                                imagejpeg($newImage, $fullPath, 75);
                                break;
                            case 'image/png':
                                imagepng($newImage, $fullPath, 6);
                                break;
                            case 'image/gif':
                                imagegif($newImage, $fullPath);
                                break;
                            case 'image/webp':
                                imagewebp($newImage, $fullPath, 75);
                                break;
                        }

                        imagedestroy($img);
                        imagedestroy($newImage);

                        $newImagePaths[] = Storage::url($imageName);
                    } catch (Exception $e) {
                        Log::error('Image processing failed during update: ' . $e->getMessage());
                        continue;
                    }
                }
            }

            // Combine existing images (not deleted) with new images
            $finalImagePaths = array_merge($existingImages, $newImagePaths);
            
            // Ensure we don't exceed 5 images
            if (count($finalImagePaths) > 5) {
                $finalImagePaths = array_slice($finalImagePaths, 0, 5);
            }

            // Update project images
            $project->project_images = json_encode($finalImagePaths);
            $project->save();

            DB::commit();

            // Refresh user data
            $user->refresh();

            return response()->json([
                'status' => 200,
                'message' => 'Portfolio updated successfully',
                'data' => [
                    'user' => $user,
                    'project' => $project
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error('Portfolio update error: ' . $th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred while updating portfolio',
            ], 500);
        }
    }


    // Delete Portfolio Project
    public function deletePortfolio(Request $request)
    {
        // Validate Request
        $validate = Validator::make($request->all(), [
            'portfolio_id' => 'required|exists:portfolio_project,portfolio_id',
        ]);

        if ($validate->fails()) {
            Log::error($validate->errors());
            return response()->json([
                'status' => 422,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $user = $request->user();
            $portfolioId = $request->portfolio_id;

            // Find the portfolio project
            $project = PortfolioProject::where('portfolio_id', $portfolioId)
                                    ->where('user_id', $user->id)
                                    ->first();

            if (!$project) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Portfolio project not found or you don\'t have permission to delete it',
                ], 404);
            }

            // Delete associated images from storage
            if (!empty($project->project_images)) {
                try {
                    $images = json_decode($project->project_images, true);
                    
                    if (is_array($images)) {
                        foreach ($images as $imageUrl) {
                            try {
                                // Extract the storage path from URL
                                if (strpos($imageUrl, '/storage/') !== false) {
                                    $storagePath = str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH));
                                    if (Storage::disk('public')->exists($storagePath)) {
                                        Storage::disk('public')->delete($storagePath);
                                        Log::info('Deleted image: ' . $storagePath);
                                    }
                                }
                            } catch (Exception $e) {
                                Log::error('Failed to delete image: ' . $imageUrl . ' - ' . $e->getMessage());
                                // Continue with deletion even if some images fail to delete
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error parsing project images JSON: ' . $e->getMessage());
                    // Continue with deletion even if image cleanup fails
                }
            }

            // Delete the portfolio project from database
            $project->delete();

            DB::commit();

            // Refresh user data
            $user->refresh();

            return response()->json([
                'status' => 200,
                'message' => 'Portfolio project deleted successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);

        } catch (Exception $th) {
            DB::rollBack();
            Log::error('Portfolio deletion error: ' . $th->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Server error occurred while deleting portfolio',
            ], 500);
        }
    }


}
