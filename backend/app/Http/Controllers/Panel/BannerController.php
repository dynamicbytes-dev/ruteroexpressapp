<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Gestión de tarjetas de publicidad y banners promocionales del sistema.
 * El Auditor Principal o los auditores pueden crear, editar, subir imágenes
 * y organizar los banners que consumen las aplicaciones móviles.
 */
class BannerController extends Controller
{
    public function index(Request $request): View
    {
        $query = Banner::query()->with(['city', 'creator']);

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('subtitle', 'LIKE', "%{$q}%")
                    ->orWhere('tag', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('target_role', $request->role);
        }

        if ($request->filled('activo')) {
            $query->where('is_active', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        $banners = $query->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('panel.banners.index', [
            'banners' => $banners,
        ]);
    }

    public function create(): View
    {
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.banners.create', [
            'ciudades' => $ciudades,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'background_color' => ['nullable', 'string', 'max:32'],
            'background_color_end' => ['nullable', 'string', 'max:32'],
            'text_color' => ['nullable', 'string', 'max:32'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'action_text' => ['nullable', 'string', 'max:50'],
            'target_role' => ['required', 'in:passenger,driver,all'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }

        Banner::create([
            'title' => $datos['title'],
            'subtitle' => $datos['subtitle'] ?? null,
            'tag' => $datos['tag'] ?? null,
            'image_path' => $imagePath,
            'image_url' => $datos['image_url'] ?? null,
            'background_color' => $datos['background_color'] ?: '#310F38',
            'background_color_end' => $datos['background_color_end'] ?: null,
            'text_color' => $datos['text_color'] ?: '#FFFFFF',
            'action_url' => $datos['action_url'] ?? null,
            'action_text' => $datos['action_text'] ?: 'Ver más',
            'target_role' => $datos['target_role'],
            'city_id' => $datos['city_id'] ?? null,
            'priority' => (int) ($datos['priority'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $datos['starts_at'] ?? null,
            'expires_at' => $datos['expires_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('panel.banners.index')
            ->with('status', 'Tarjeta de publicidad creada con éxito.');
    }

    public function edit(Banner $banner): View
    {
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.banners.edit', [
            'banner' => $banner,
            'ciudades' => $ciudades,
        ]);
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $datos = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:50'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:4096'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'background_color' => ['nullable', 'string', 'max:32'],
            'background_color_end' => ['nullable', 'string', 'max:32'],
            'text_color' => ['nullable', 'string', 'max:32'],
            'action_url' => ['nullable', 'string', 'max:255'],
            'action_text' => ['nullable', 'string', 'max:50'],
            'target_role' => ['required', 'in:passenger,driver,all'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('eliminar_imagen') && $banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
            $banner->image_path = null;
        }

        if ($request->hasFile('image')) {
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            $banner->image_path = $request->file('image')->store('banners', 'public');
        }

        $banner->fill([
            'title' => $datos['title'],
            'subtitle' => $datos['subtitle'] ?? null,
            'tag' => $datos['tag'] ?? null,
            'image_url' => $datos['image_url'] ?? null,
            'background_color' => $datos['background_color'] ?: '#310F38',
            'background_color_end' => $datos['background_color_end'] ?: null,
            'text_color' => $datos['text_color'] ?: '#FFFFFF',
            'action_url' => $datos['action_url'] ?? null,
            'action_text' => $datos['action_text'] ?: 'Ver más',
            'target_role' => $datos['target_role'],
            'city_id' => $datos['city_id'] ?? null,
            'priority' => (int) ($datos['priority'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'starts_at' => $datos['starts_at'] ?? null,
            'expires_at' => $datos['expires_at'] ?? null,
        ]);

        $banner->save();

        return redirect()->route('panel.banners.index')
            ->with('status', 'Tarjeta publicitaria actualizada correctamente.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }

        $banner->delete();

        return redirect()->route('panel.banners.index')
            ->with('status', 'Banner publicitario eliminado con éxito.');
    }

    public function toggle(Banner $banner): RedirectResponse
    {
        $banner->update([
            'is_active' => ! $banner->is_active,
        ]);

        $mensaje = $banner->is_active
            ? 'El banner ahora está visible en la aplicación.'
            : 'El banner ha sido pausado.';

        return back()->with('status', $mensaje);
    }
}
