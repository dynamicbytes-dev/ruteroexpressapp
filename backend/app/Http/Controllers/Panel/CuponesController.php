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

        if ($request->has('q') && $request->q !== '') {
            $q = trim($request->q);
            $query->where('code', 'LIKE', "%{$q}%");
        }

        if ($request->has('city') && $request->city !== '') {
            $query->where('city_id', $request->city);
        }

        if ($request->has('activo')) {
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

    public function show(Coupon $cupo): View
    {
        $cupo->load(['creator', 'city', 'usages.user', 'usages.trip']);

        return view('panel.cupones.show', [
            'cupo' => $cupo,
        ]);
    }

    public function edit(Coupon $cupo): View
    {
        $ciudades = City::query()->orderBy('name')->get();

        return view('panel.cupones.edit', [
            'cupo' => $cupo,
            'ciudades' => $ciudades,
        ]);
    }

    public function update(Request $request, Coupon $cupo): RedirectResponse
    {
        $datos = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:coupons,code,'.$cupo->getKey()],
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

        $cupo->update([
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
            ->route('panel.cupones.show', $cupo)
            ->with('status', 'Cupón actualizado.');
    }

    public function destroy(Request $request, Coupon $cupo): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'boolean'],
        ]);

        if (! $request->validated('confirm')) {
            return back()->withErrors(['confirm' => 'Confirma que deseas desactivar este cupón.']);
        }

        $cupo->update(['is_active' => false]);

        return redirect()
            ->route('panel.cupones.index')
            ->with('status', 'Cupón desactivado. Los viajes ya creados con este cupón no cambian.');
    }
}
