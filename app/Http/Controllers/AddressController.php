<?php

namespace App\Http\Controllers;

use App\Services\AddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Upravljanje sačuvanim adresama (Profile stranica).
 *
 * IDOR: parametar rute je namerno običan int, NE type-hint Address — implicitni
 * route-model-binding bi razrešio adresu globalno (preko svih korisnika) pre
 * bilo kakve provere. Umesto toga, lookup ide kroz $request->user()->addresses()
 * pa tuđa adresa daje 404, identično nepostojećoj (ne otkriva se da ID postoji).
 * Gate::authorize posle toga je odbrana u dubini (bazni Controller u ovom
 * Laravel 12 skeletu nema AuthorizesRequests trait, pa nema $this->authorize()).
 */
class AddressController extends Controller
{
    public function __construct(private AddressService $addresses)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $this->addresses->create($request->user(), $validated);

        return back()->with('success', 'Adresa je sačuvana.');
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $addressModel = $request->user()->addresses()->findOrFail($address);
        Gate::authorize('update', $addressModel);

        $validated = $request->validate($this->rules());

        $this->addresses->update($addressModel, $validated);

        return back()->with('success', 'Adresa je izmenjena.');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $addressModel = $request->user()->addresses()->findOrFail($address);
        Gate::authorize('delete', $addressModel);

        $this->addresses->delete($addressModel);

        return back()->with('success', 'Adresa je obrisana.');
    }

    public function setDefault(Request $request, int $address): RedirectResponse
    {
        $addressModel = $request->user()->addresses()->findOrFail($address);
        Gate::authorize('update', $addressModel);

        $this->addresses->setDefault($addressModel);

        return back()->with('success', 'Podrazumevana adresa je postavljena.');
    }

    private function rules(): array
    {
        return [
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'country' => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ];
    }
}
