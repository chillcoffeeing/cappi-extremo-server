<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;
use App\Http\Resources\RepresentativeResource;
use App\Support\PublicPhoto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RepresentativeController extends Controller
{
    public function show(): RepresentativeResource
    {
        return new RepresentativeResource(request()->user());
    }

    public function updateContact(Request $request): RepresentativeResource
    {
        $user = request()->user();
        // F-056 (api-A-10): un correo ya usado por otra cuenta -> 422, no 500
        // por el índice único de users.email.
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telefono' => ['required', 'string', 'max:40'],
        ], [
            'correo.unique' => 'Ese correo ya está registrado en otra cuenta.',
        ]);
        $user->update(['name' => $data['nombre'], 'last_name' => $data['apellido'], 'email' => $data['correo'], 'phone' => $data['telefono']]);

        return new RepresentativeResource($user->refresh());
    }

    public function updateIdentification(Request $request): RepresentativeResource
    {
        $user = request()->user();
        // F-056 (api-A-10): una cédula ya usada por otra cuenta -> 422, no 500.
        $data = $request->validate([
            'documento' => ['required', 'string', 'max:40', Rule::unique('users', 'identification')->ignore($user->id)],
            'relacion' => ['required', 'string', 'max:80'],
        ], [
            'documento.unique' => 'Ese documento ya está registrado en otra cuenta.',
        ]);
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
        $previous = $user->photo_url;
        $path = $request->file('foto')->store('profiles', 'public');
        $user->update(['photo_url' => $path]);
        // F-061 (api-A-7): solo se borra la foto anterior propia de `profiles/`.
        if ($previous !== $path) {
            PublicPhoto::deleteIfOwned($previous, 'profiles');
        }

        return new RepresentativeResource($user->refresh());
    }
}
