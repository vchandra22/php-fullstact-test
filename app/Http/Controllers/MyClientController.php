<?php

namespace App\Http\Controllers;

use App\Models\MyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class MyClientController extends Controller
{
    /**
     * Kunci prefix untuk Redis
     *
     * @var string
     */
    protected $redisPrefix = 'client:';

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug($name, $ignoreId = null)
    {
        // Buat slug dasar dari nama
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Loop sampai menemukan slug yang unik
        while (true) {
            // Cek apakah slug sudah ada (kecuali untuk ID yang ingin diabaikan pada kasus update)
            $query = MyClient::where('slug', $slug);

            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }

            // Jika slug tidak ditemukan, kita sudah punya slug unik
            if ($query->doesntExist()) {
                break;
            }

            // Jika sudah ada, tambahkan counter ke slug dan coba lagi
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Retrieve all clients
     */
    public function index()
    {
        try {
            $clients = MyClient::whereNull('deleted_at')->get();

            // Jika ingin caching semua client
            $cacheKey = $this->redisPrefix . 'all';
            Redis::set($cacheKey, $clients->toJson());
            Redis::expire($cacheKey, 3600); // Cache selama 1 jam

            return response()->json([
                'status' => 'success',
                'data' => $clients,
                'count' => $clients->count()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve clients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:250',
                'slug' => 'nullable|string|max:100',
                'is_project' => 'required|in:0,1',
                'self_capture' => 'required|string|size:1',
                'client_prefix' => 'required|string|size:4',
                'client_logo' => 'nullable|image|max:2048',
                'address' => 'nullable|string',
                'phone_number' => 'nullable|string|max:50',
                'city' => 'nullable|string|max:50',
            ]);

            // Generate slug jika tidak ada
            if (empty($validated['slug'])) {
                $validated['slug'] = $this->generateUniqueSlug($validated['name']);
            } else {
                // Jika slug diisi manual, pastikan tetap unik
                $validated['slug'] = $this->generateUniqueSlug($validated['slug']);
            }

            if ($request->hasFile('client_logo')) {
                $path = $request->file('client_logo')->store('logos', 's3');
                $validated['client_logo'] = Storage::disk('s3')->url($path);
            }

            $client = MyClient::create($validated);

            // Cache di Redis
            $cacheKey = $this->redisPrefix . $client->slug;
            Redis::set($cacheKey, $client->toJson());
            Redis::expire($cacheKey, 86400); // Cache selama 24 jam

            return response()->json([
                'status' => 'success',
                'message' => 'Client created successfully',
                'data' => $client
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing client
     */
    public function update(Request $request, $id)
    {
        try {
            $client = MyClient::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:250',
                'slug' => 'nullable|string|max:100',
                'is_project' => 'sometimes|required|in:0,1',
                'self_capture' => 'sometimes|required|string|size:1',
                'client_prefix' => 'sometimes|required|string|size:4',
                'client_logo' => 'nullable|image|max:2048',
                'address' => 'nullable|string',
                'phone_number' => 'nullable|string|max:50',
                'city' => 'nullable|string|max:50',
            ]);

            // Get old slug before update
            $oldSlug = $client->slug;

            // Jika name berubah dan slug tidak diisi, generate slug baru
            if (isset($validated['name']) && $validated['name'] !== $client->name && !isset($validated['slug'])) {
                $validated['slug'] = $this->generateUniqueSlug($validated['name'], $client->id);
            }
            // Jika slug diisi manual, pastikan tetap unik
            elseif (isset($validated['slug'])) {
                $validated['slug'] = $this->generateUniqueSlug($validated['slug'], $client->id);
            }

            if ($request->hasFile('client_logo')) {
                // Delete old logo if exists and not a default image
                if ($client->client_logo && !str_contains($client->client_logo, 'default')) {
                    $oldPath = str_replace(Storage::disk('s3')->url(''), '', $client->client_logo);
                    Storage::disk('s3')->delete($oldPath);
                }

                $path = $request->file('client_logo')->store('logos', 's3');
                $validated['client_logo'] = Storage::disk('s3')->url($path);
            }

            $client->update($validated);

            // Update Redis cache
            $newCacheKey = $this->redisPrefix . $client->slug;
            Redis::set($newCacheKey, $client->toJson());
            Redis::expire($newCacheKey, 86400); // Cache selama 24 jam

            // Remove old cache if slug changed
            if ($oldSlug !== $client->slug) {
                Redis::del($this->redisPrefix . $oldSlug);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Client updated successfully',
                'data' => $client
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * "Delete" a client (soft delete)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $client = MyClient::findOrFail($id);

            $client->delete();

            Redis::del($this->redisPrefix . $client->slug);

            Redis::del($this->redisPrefix . 'all');

            return response()->json([
                'status' => 'success',
                'message' => 'Client deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete client',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($slug)
    {
        try {
            $cacheKey = $this->redisPrefix . $slug;
            $cachedClient = Redis::get($cacheKey);

            if ($cachedClient) {
                $client = json_decode($cachedClient);
            } else {
                $client = MyClient::where('slug', $slug)
                    ->whereNull('deleted_at')
                    ->firstOrFail();

                Redis::set($cacheKey, $client->toJson());
                Redis::expire($cacheKey, 86400); // Cache selama 24 jam
            }

            return response()->json([
                'status' => 'success',
                'data' => $client
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
