<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientService
{
    public function create(array $data, ?UploadedFile $logo = null)
    {
        if ($logo) {
            $data['client_logo'] = $this->uploadLogoToS3($logo);
        }

        $client = Client::create($data);

        $this->storeInRedis($client);

        return $client;
    }

    public function find($id)
    {
        return Client::find($id);
    }

    public function getAll()
    {
        return Client::whereNull('deleted_at')->get();
    }

    public function update($id, array $data, ?UploadedFile $logo = null)
    {
        $client = Client::findOrFail($id);

        if ($logo) {
            if ($client->client_logo !== 'no-image.jpg') {
                $this->deleteLogoFromS3($client->client_logo);
            }

            $data['client_logo'] = $this->uploadLogoToS3($logo);
        }

        $this->deleteFromRedis($client->slug);

        $client->update($data);

        $this->storeInRedis($client);

        return $client;
    }

    public function delete($id)
    {
        $client = Client::findOrFail($id);

        $this->deleteFromRedis($client->slug);

        $client->delete();

        return $client;
    }

    private function storeInRedis(Client $client)
    {
        Redis::set(
            "client:{$client->slug}",
            json_encode($client->toArray()),
            'EX',
            60 * 60 * 24 * 30 // 30 hari
        );
    }

    private function deleteFromRedis($slug)
    {
        Redis::del("client:{$slug}");
    }

    private function uploadLogoToS3(UploadedFile $file)
    {
        $filename = 'client-logos/' . Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('s3')->put($filename, file_get_contents($file));

        return Storage::disk('s3')->url($filename);
    }

    private function deleteLogoFromS3($logoUrl)
    {
        $path = parse_url($logoUrl, PHP_URL_PATH);
        $path = ltrim($path, '/');

        Storage::disk('s3')->delete($path);
    }
}
