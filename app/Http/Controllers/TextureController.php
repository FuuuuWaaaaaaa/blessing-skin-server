<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Texture;
use App\Models\User;
use Blessing\Minecraft;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class TextureController extends Controller
{
    public function __construct()
    {
        $this->middleware('cache.headers:public;max_age='.option('cache_expire_time'))
            ->only(['json']);

        $this->middleware('cache.headers:etag;public;max_age='.option('cache_expire_time'))
            ->only([
                'preview',
                'raw',
                'texture',
                'avatarByPlayer',
                'avatarByUser',
                'avatarByTexture',
            ]);
    }

    public function json($player)
    {
        $player = Player::where('name', $player)->firstOrFail();
        $isBanned = $player->user->permission === User::BANNED;
        abort_if($isBanned, 403, trans('general.player-banned'));

        return response()->json($player)->setLastModified($player->last_modified);
    }

    public function previewByHash(Minecraft $minecraft, Request $request, $hash)
    {
        $this->ensureSafeTextureKey($hash);
        $texture = Texture::where('hash', $hash)->firstOrFail();

        return $this->preview($minecraft, $request, $texture);
    }

    public function preview(Minecraft $minecraft, Request $request, Texture $texture)
    {
        $tid = $texture->tid;
        $hash = $texture->hash;

        $disk = Storage::disk('textures');
        abort_if($disk->missing($hash), 404);

        $height = min(max((int) $request->query('height', 200), 1), 2048);
        if (!$this->hasGdSupport()) {
            return $this->fallbackPreview($disk->get($hash), $texture, $height, $disk->lastModified($hash));
        }

        $usePNG = $request->has('png') || !(imagetypes() & IMG_WEBP);
        $format = $usePNG ? 'png' : 'webp';
        $now = Carbon::now();
        $response = Cache::remember(
            'preview-t'.$tid."-$format",
            option('enable_preview_cache') ? $now->addYear() : $now->addMinute(),
            function () use ($minecraft, $disk, $texture, $hash, $height, $usePNG) {
                $file = $disk->get($hash);
                if ($texture->type === 'cape') {
                    $image = $minecraft->renderCape($file, $height);
                } else {
                    $image = $minecraft->renderSkin($file, 12, $texture->type === 'alex');
                }

                $lastModified = $disk->lastModified($hash);

                // TODO: refactor
                return \Intervention\Image\ImageManagerStatic::configure(['driver' => 'gd'])->make($image)
                    ->response($usePNG ? 'png' : 'webp', 100)
                    ->setLastModified(Carbon::createFromTimestamp($lastModified));
            }
        );

        return $response;
    }

    public function raw($tid)
    {
        abort_unless(option('allow_downloading_texture'), 403);

        $texture = Texture::findOrFail($tid);

        return $this->texture($texture->hash);
    }

    public function texture(string $hash)
    {
        $this->ensureSafeTextureKey($hash);
        $disk = Storage::disk('textures');
        abort_if($disk->missing($hash), 404);

        $lastModified = Carbon::createFromTimestamp($disk->lastModified($hash));

        return response($disk->get($hash))
            ->withHeaders([
                'Content-Type' => 'image/png',
                'Content-Length' => $disk->size($hash),
            ])
            ->setLastModified($lastModified);
    }

    public function avatarByPlayer(Minecraft $minecraft, Request $request, $name)
    {
        $player = Player::where('name', $name)->firstOrFail();

        return $this->avatar($minecraft, $request, $player->skin);
    }

    public function avatarByUser(Minecraft $minecraft, Request $request, $uid)
    {
        $texture = Texture::find(optional(User::find($uid))->avatar);

        return $this->avatar($minecraft, $request, $texture);
    }

    public function avatarByHash(Minecraft $minecraft, Request $request, $hash)
    {
        $this->ensureSafeTextureKey($hash);
        $texture = Texture::where('hash', $hash)->first();

        return $this->avatar($minecraft, $request, $texture);
    }

    public function avatarByTexture(Minecraft $minecraft, Request $request, $tid)
    {
        $texture = Texture::find($tid);

        return $this->avatar($minecraft, $request, $texture);
    }

    protected function avatar(Minecraft $minecraft, Request $request, ?Texture $texture)
    {
        if (!empty($texture) && $texture->type !== 'steve' && $texture->type !== 'alex') {
            return abort(422);
        }

        $size = min(max((int) $request->query('size', 100), 1), 1024);
        $mode = $request->has('3d') ? '3d' : '2d';

        $disk = Storage::disk('textures');
        if (is_null($texture) || $disk->missing($texture->hash)) {
            if (!$this->hasGdSupport()) {
                return $this->fallbackSvgResponse(
                    (string) file_get_contents(resource_path("misc/textures/avatar$mode.png")),
                    $size,
                    $size,
                    0,
                    0,
                    null,
                    null,
                );
            }

            $usePNG = $request->has('png') || !(imagetypes() & IMG_WEBP);
            // TODO: refactor
            return \Intervention\Image\ImageManagerStatic::configure(['driver' => 'gd'])->make(resource_path("misc/textures/avatar$mode.png"))
                ->resize($size, $size)
                ->response($usePNG ? 'png' : 'webp', 100);
        }

        if (!$this->hasGdSupport()) {
            return $this->fallbackSvgResponse(
                $disk->get($texture->hash),
                $size,
                $size,
                8,
                8,
                $disk->lastModified($texture->hash),
                '8 8 8 8',
            );
        }

        $usePNG = $request->has('png') || !(imagetypes() & IMG_WEBP);
        $format = $usePNG ? 'png' : 'webp';
        $hash = $texture->hash;
        $now = Carbon::now();
        $response = Cache::remember(
            'avatar-'.$mode.'-t'.$texture->tid.'-s'.$size."-$format",
            option('enable_avatar_cache') ? $now->addYear() : $now->addMinute(),
            function () use ($minecraft, $disk, $hash, $size, $mode, $usePNG) {
                $file = $disk->get($hash);
                if ($mode === '3d') {
                    $image = $minecraft->render3dAvatar($file, 25);
                } else {
                    $image = $minecraft->render2dAvatar($file, 25);
                }

                $lastModified = Carbon::createFromTimestamp($disk->lastModified($hash));

                // TODO: refactor
                return \Intervention\Image\ImageManagerStatic::configure(['driver' => 'gd'])->make($image)
                    ->resize($size, $size)
                    ->response($usePNG ? 'png' : 'webp', 100)
                    ->setLastModified($lastModified);
            }
        );

        return $response;
    }

    private function ensureSafeTextureKey(string $hash): void
    {
        abort_unless(
            preg_match('/\A[a-zA-Z0-9._-]+\z/', $hash) === 1
                && basename($hash) === $hash,
            404
        );
    }

    private function hasGdSupport(): bool
    {
        return function_exists('imagetypes')
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor');
    }

    private function fallbackPreview(string $contents, Texture $texture, int $height, int $lastModified)
    {
        [$sourceWidth, $sourceHeight] = $this->getImageDimensions($contents);
        $width = $texture->type === 'cape'
            ? max(1, (int) round($height * $sourceWidth / $sourceHeight))
            : $height;

        return $this->fallbackSvgResponse(
            $contents,
            $width,
            $height,
            $sourceWidth,
            $sourceHeight,
            $lastModified,
            null,
        );
    }

    private function fallbackSvgResponse(
        string $contents,
        int $width,
        int $height,
        int $viewBoxWidth,
        int $viewBoxHeight,
        ?int $lastModified,
        ?string $viewBox = null,
    ) {
        [$sourceWidth, $sourceHeight] = $this->getImageDimensions($contents);
        $viewBoxWidth = $viewBoxWidth > 0 ? $viewBoxWidth : $sourceWidth;
        $viewBoxHeight = $viewBoxHeight > 0 ? $viewBoxHeight : $sourceHeight;
        $viewBox ??= "0 0 $viewBoxWidth $viewBoxHeight";
        $image = base64_encode($contents);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="'.$viewBox.'" preserveAspectRatio="xMidYMid meet">'
            .'<image href="data:image/png;base64,'.$image.'" width="'.$sourceWidth.'" height="'.$sourceHeight.'" preserveAspectRatio="none"/></svg>';

        $response = response($svg)->header('Content-Type', 'image/svg+xml');
        if ($lastModified !== null) {
            $response->setLastModified(Carbon::createFromTimestamp($lastModified));
        }

        return $response;
    }

    private function getImageDimensions(string $contents): array
    {
        $dimensions = function_exists('getimagesizefromstring')
            ? @getimagesizefromstring($contents)
            : null;

        return [
            max(1, (int) ($dimensions[0] ?? 64)),
            max(1, (int) ($dimensions[1] ?? 64)),
        ];
    }
}
