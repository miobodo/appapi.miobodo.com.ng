<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArtisanController extends Controller
{
    /**
     * Fetch all artisans with location-based prioritization
     */
    public function fetchAllArtisans(Request $request)
    {
        try {
            $user = $request->user();

            // Get artisans with location-based ordering
            $artisans = $this->getArtisansWithLocationPriority($user);

            return response()->json([
                'success' => true,
                'message' => 'All artisans fetched successfully',
                'data' => $artisans,
                'count' => count($artisans)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching all artisans: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch artisans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Fetch artisans by specific service with location-based prioritization
     */
    public function fetchArtisansByService(Request $request, $service)
    {
        try {
            $user = $request->user();

            // Validate service parameter
            if (empty($service)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Service parameter is required'
                ], 400);
            }

            // Get artisans with location-based ordering filtered by service
            $artisans = $this->getArtisansWithLocationPriority($user, strtolower($service));

            Log::info("fetched artisans:",$artisans);

            return response()->json([
                'success' => true,
                'message' => "Artisans for service '{$service}' fetched successfully",
                'data' => $artisans,
                'count' => count($artisans),
                'service' => $service
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error fetching artisans for service {$service}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => "Failed to fetch artisans for service '{$service}'",
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Fetch only verified artisans with location-based prioritization
     */
    public function fetchVerifiedArtisans(Request $request)
    {
        try {
            $user = $request->user();

            // Get verified artisans with location-based ordering
            $artisans = $this->getArtisansWithLocationPriority($user, null, true);

            return response()->json([
                'success' => true,
                'message' => 'Verified artisans fetched successfully',
                'data' => $artisans,
                'count' => count($artisans)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching verified artisans: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verified artisans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get artisans with location-based priority ordering
     * Priority: User's LGA > User's State > Others
     */
    private function getArtisansWithLocationPriority($user, $service = null, $verifiedOnly = false)
    {
        $userLga = $user->lga;
        $userState = $user->state;

        // Base query for artisans (exclude the current user and only account_type = 'artisan')
        $query = User::where('account_type', 'artisan')
                    ->where('id', '!=', $user->id)
                    ->with('portfolioProjects');

        // Apply service filter if provided
        if ($service) {
            $query->where('service', strtolower($service));
        }

        // Apply verified filter if requested
        if ($verifiedOnly) {
            // Assuming you have a 'verified' column or some verification logic
            // Adjust this based on your verification system
            $query->where('bvn_v_status', true)
                  ->where('email_v_status', true);
        }

        $artisans = $query->get();

        // Group artisans by location priority
        $sameLga = collect();
        $sameState = collect();
        $others = collect();

        foreach ($artisans as $artisan) {
            if ($userLga && $artisan->lga === $userLga) {
                $sameLga->push($artisan);
            } elseif ($userState && $artisan->state === $userState) {
                $sameState->push($artisan);
            } else {
                $others->push($artisan);
            }
        }

        // Shuffle each group to ensure fair distribution
        $sameLga = $sameLga->shuffle();
        $sameState = $sameState->shuffle();
        $others = $others->shuffle();

        // Merge in priority order: Same LGA, Same State, Others
        $prioritizedArtisans = $sameLga->concat($sameState)->concat($others);

        // Transform the data for Flutter consumption
        return $prioritizedArtisans->map(fn($artisan) => $this->transformArtisanData($artisan))->values()->toArray();
    }

    /**
     * Transform artisan data for Flutter consumption
     */
    private function transformArtisanData($artisan)
    {
        // Get service icon and background color
        $serviceIcon = $artisan->getServiceIconPath();
        $serviceIconBg = $artisan->getServiceIconBgColor();

        // Prepare portfolio data with proper image URLs
        $portfolio = $artisan->portfolioProjects->map(function ($project) {
            $projectImages = [];
            
            if (!empty($project->project_images)) {
                $images = is_string($project->project_images) 
                    ? json_decode($project->project_images, true) 
                    : $project->project_images;
                
                if (is_array($images)) {
                    $projectImages = array_map(function ($imagePath) {
                        if (empty($imagePath)) return null;
                        
                        // If already a full URL, return as is
                        if (str_starts_with($imagePath, 'http')) {
                            return $imagePath;
                        }
                        
                        // Handle storage paths
                        $baseUrl = rtrim(env('APP_URL'), '/').'/project/storage/app/public';
                        $cleanedPath = ltrim(preg_replace('#^/?(storage/)?#', '', $imagePath), '/');
                        return "$baseUrl/$cleanedPath";
                    }, $images);
                    
                    // Remove null values
                    $projectImages = array_filter($projectImages);
                }
            }

            return [
                'title' => $project->title ?? '',
                'portfolio_bg' => $project->portfolio_bg ?? '',
                'portfolio_id' => $project->portfolio_id ?? '',
                'role' => $project->role ?? '',
                'project_description' => $project->project_description ?? '',
                'project_images' => json_encode(array_values($projectImages))
            ];
        })->toArray();

        // Determine verification status
        $verified = ($artisan->bvn_v_status && $artisan->email_v_status) ?? false;

        return [
            'id' => $artisan->id,
            'profileid' => (string) $artisan->id,
            'name' => $artisan->fullname ?? $artisan->username ?? 'Unknown',
            'username' => $artisan->username ?? '',
            'email' => $artisan->email ?? '',
            'phonenumber' => $artisan->phone_number ?? '',
            'service' => ucfirst($artisan->service ?? 'General'),
            'bio' => $artisan->bio ?? '',
            'location' => "$artisan->lga, $artisan->state",
            'state' => $artisan->state ?? '',
            'lga' => $artisan->lga ?? '',
            'experience' => $artisan->years_of_experience,
            'rating' => (float) ($artisan->rating ?? 0),
            'status' => $artisan->status,
            'profilePic' => $artisan->profile_pic,
            'serviceIcon' => $serviceIcon,
            'serviceIconbg' => $serviceIconBg,
            'portfolio' => $portfolio,
            'verified' => $artisan->verified,
            'tier' => $artisan->tier ?? '',
            'last_seen_at' => $artisan->last_seen_at,
            'created_at' => $artisan->created_at,
            'updated_at' => $artisan->updated_at,
        ];
    }

    /**
     * Get artisan profile by ID
     */
    public function getArtisanProfile(Request $request, $profileId)
    {

        $user = $request->user();

        try {
            $artisan = User::where('account_type', 'artisan')
                          ->where('id', $profileId)
                          ->with('portfolioProjects')
                          ->whereNot('id', $user->id)
                          ->first();

            if (!$artisan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Artisan not found'
                ], 404);
            }

            $transformedData = $this->transformArtisanData($artisan);

            return response()->json([
                'success' => true,
                'message' => 'Artisan profile fetched successfully',
                'data' => $transformedData
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error fetching artisan profile {$profileId}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch artisan profile',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Search artisans by name, service, or location
     */
    public function searchArtisans(Request $request)
    {
        try {

            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $searchTerm = $request->input('search', '');
            
            if (empty($searchTerm)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search term is required'
                ], 400);
            }

            $query = User::where('account_type', 'artisan')
                        ->where('id', '!=', $user->id)
                        ->with('portfolioProjects')
                        ->where(function ($q) use ($searchTerm) {
                            $q->where('fullname', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('username', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('service', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('location', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('state', 'LIKE', "%{$searchTerm}%")
                              ->orWhere('lga', 'LIKE', "%{$searchTerm}%");
                        });

            $artisans = $query->get();

            // Apply location-based priority to search results
            $userLga = $user->lga;
            $userState = $user->state;

            $sameLga = collect();
            $sameState = collect();
            $others = collect();

            foreach ($artisans as $artisan) {
                if ($userLga && $artisan->lga === $userLga) {
                    $sameLga->push($artisan);
                } elseif ($userState && $artisan->state === $userState) {
                    $sameState->push($artisan);
                } else {
                    $others->push($artisan);
                }
            }

            // Shuffle each group for fair distribution
            $sameLga = $sameLga->shuffle();
            $sameState = $sameState->shuffle();
            $others = $others->shuffle();

            // Merge in priority order
            $prioritizedResults = $sameLga->concat($sameState)->concat($others);

            $transformedResults = $prioritizedResults->map(function ($artisan) {
                return $this->transformArtisanData($artisan);
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Search completed successfully',
                'data' => $transformedResults,
                'count' => count($transformedResults),
                'search_term' => $searchTerm
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error searching artisans: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}