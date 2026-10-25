<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Devuelve las tarjetas y banners de publicidad activos para la app móvil.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $rol = $request->input('role', $user?->role?->value ?? 'passenger');
        $ciudadId = $request->filled('city_id')
            ? (int) $request->input('city_id')
            : $user?->city_id;

        $banners = Banner::query()
            ->active()
            ->forRole($rol)
            ->forCity($ciudadId)
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->get();

        $formateados = $banners->map(function (Banner $banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'tag' => $banner->tag,
                'image_url' => $banner->display_image_url,
                'background_color' => $banner->background_color ?: '#310F38',
                'background_color_end' => $banner->background_color_end ?: '#9A0065',
                'text_color' => $banner->text_color ?: '#FFFFFF',
                'action_url' => $banner->action_url,
                'action_text' => $banner->action_text ?: 'Ver más',
                'target_role' => $banner->target_role,
            ];
        });

        return response()->json([
            'data' => $formateados,
        ]);
    }
}
