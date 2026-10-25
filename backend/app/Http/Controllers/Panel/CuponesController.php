<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cupones de descuento: el auditor crea cupones porcentuales que los
 * pasajeros pueden usar al pedir un viaje. El descuento reduce lo que
 * paga el pasajero; el conductor recibe su ganancia completa y el sistema
 * cubre la diferencia (subsidio).
 */
class CuponesController extends Controller
{
    public function index(Request $request): View
    {
        $query = Coupon::query()->with(['creator', 'city', 'usages']);

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where('code', 'LIKE', "%{$q}%");
        }

        if ($request->filled('city')) {
            $query->where('city_id', $request->city);
        }

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('is_active', filter_var($request->activo, FILTER_VALIDATE_BOOLEAN));
        }

        $cupones = $query->latest()->paginate(20)->withQueryString();
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.cupones.index', [
            'cupones' => $cupones,
            'ciudades' => $ciudades,
        ]);
    }

    public function create(): View
    {
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.cupones.create', [
            'ciudades' => $ciudades,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code'],
            'discount_percent' => ['required', 'numeric', 'between:0,100'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses_global' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'is_active' => ['boolean'],
        ]);

        Coupon::create([
            'code' => mb_strtoupper(trim($datos['code'])),
            'discount_percent' => $datos['discount_percent'],
            'min_purchase' => $datos['min_purchase'] ?? null,
            'max_discount' => $datos['max_discount'] ?? null,
            'valid_from' => $datos['valid_from'],
            'valid_until' => $datos['valid_until'] ?? null,
            'max_uses_global' => $datos['max_uses_global'] ?? null,
            'max_uses_per_user' => $datos['max_uses_per_user'] ?? null,
            'city_id' => $datos['city_id'] ?? null,
            'created_by' => $request->user()->getKey(),
            'is_active' => $datos['is_active'] ?? true,
        ]);

        return redirect()
            ->route('panel.cupones.index')
            ->with('status', 'Cupón creado. Los pasajeros ya pueden usarlo al pedir un viaje.');
    }

    public function show(Coupon $coupon): View
    {
        $coupon->load(['creator', 'city', 'usages.user', 'usages.trip']);

        return view('panel.cupones.show', [
            'cupo' => $coupon,
        ]);
    }

    public function edit(Coupon $coupon): View
    {
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.cupones.edit', [
            'cupo' => $coupon,
            'ciudades' => $ciudades,
        ]);
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $datos = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code,'.$coupon->getKey()],
            'discount_percent' => ['required', 'numeric', 'between:0,100'],
            'min_purchase' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'max_uses_global' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'is_active' => ['boolean'],
        ]);

        $coupon->update([
            'code' => mb_strtoupper(trim($datos['code'])),
            'discount_percent' => $datos['discount_percent'],
            'min_purchase' => $datos['min_purchase'] ?? null,
            'max_discount' => $datos['max_discount'] ?? null,
            'valid_from' => $datos['valid_from'],
            'valid_until' => $datos['valid_until'] ?? null,
            'max_uses_global' => $datos['max_uses_global'] ?? null,
            'max_uses_per_user' => $datos['max_uses_per_user'] ?? null,
            'city_id' => $datos['city_id'] ?? null,
            'is_active' => $datos['is_active'] ?? true,
        ]);

        return redirect()
            ->route('panel.cupones.show', $coupon)
            ->with('status', 'Cupón actualizado.');
    }

    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        $mensaje = $coupon->is_active
            ? "Cupón {$coupon->code} activado. Los pasajeros pueden aplicarlo en sus viajes."
            : "Cupón {$coupon->code} desactivado.";

        return back()->with('status', $mensaje);
    }

    public function destroy(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => false]);

        return redirect()
            ->route('panel.cupones.index')
            ->with('status', 'Cupón desactivado. Los viajes ya creados con este cupón no cambian.');
    }
}
