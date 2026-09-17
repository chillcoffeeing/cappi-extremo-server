<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\RepresentativeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RepresentativeController extends Controller
{
    public function show(): RepresentativeResource
    {
        return new RepresentativeResource(request()->user());
    }

    public function updateContact(Request $request): RepresentativeResource
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email'],
            'telefono' => ['required', 'string', 'max:40'],
        ]);
        $user = request()->user();
        $user->update(['name' => $data['nombre'], 'last_name' => $data['apellido'], 'email' => $data['correo'], 'phone' => $data['telefono']]);

        return new RepresentativeResource($user->refresh());
    }

    public function updateIdentification(Request $request): RepresentativeResource
    {
        $data = $request->validate(['documento' => ['required', 'string', 'max:40'], 'relacion' => ['required', 'string', 'max:80']]);
        $user = request()->user();
        $user->update(['identification' => $data['documento'], 'relationship' => $data['relacion']]);

        return new RepresentativeResource($user->refresh());
    }

    public function updatePickup(Request $request): RepresentativeResource
    {
        $data = $request->validate(['encargado' => ['nullable', 'array']]);
        request()->user()->update(['pickup_contact' => $data['encargado'] ?? null]);

        return new RepresentativeResource(request()->user()->refresh());
    }

    public function updatePhoto(UploadPhotoRequest $request): RepresentativeResource
    {
        $user = request()->user();
        if ($user->photo_url) {
            Storage::disk('public')->delete($user->photo_url);
        }
        $path = $request->file('foto')->store('profiles', 'public');
        $user->update(['photo_url' => $path]);

        return new RepresentativeResource($user->refresh());
    }
}
