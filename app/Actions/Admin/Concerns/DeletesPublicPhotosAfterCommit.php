<?php

namespace App\Actions\Admin\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * F-053: las fotos (perfil del representante y de participantes, disco
 * `public`) se borran solo cuando la transaccion confirma. Si hay rollback el
 * callback se descarta y los archivos quedan intactos. Los comprobantes de
 * pago (disco `local`) nunca pasan por aqui: se conservan.
 */
trait DeletesPublicPhotosAfterCommit
{
    /**
     * @param  list<string|null>  $paths
     * @return list<string> rutas que se borraran tras el commit
     */
    protected function deletePublicPhotosAfterCommit(array $paths): array
    {
        $paths = $this->photoPaths($paths);

        if ($paths !== []) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($paths));
        }

        return $paths;
    }

    /**
     * @param  list<string|null>  $paths
     * @return list<string>
     */
    protected function photoPaths(array $paths): array
    {
        return array_values(array_unique(array_filter(
            $paths,
            // photo_url guarda una ruta relativa del disco public; una URL
            // absoluta (dato legado o externo) no es un archivo nuestro.
            fn (?string $path): bool => is_string($path) && $path !== '' && ! filter_var($path, FILTER_VALIDATE_URL),
        )));
    }
}
