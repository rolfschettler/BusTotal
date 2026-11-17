<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Ansprechpartner und Zusatzadressen eines Kunden. */
class CustomerChildController extends Controller
{
    // ---------- Ansprechpartner ----------

    public function storeContact(Request $request, Customer $customer): JsonResponse
    {
        $data = $this->contactData($request);

        $contact = DB::transaction(function () use ($customer, $data) {
            // Nur ein Hauptansprechpartner: den bisherigen zurücksetzen.
            if (! empty($data['is_primary'])) {
                $customer->contacts()->update(['is_primary' => false]);
            }

            return $customer->contacts()->create($data);
        });

        return response()->json($contact->fresh(), 201);
    }

    public function updateContact(Request $request, Customer $customer, Contact $contact): JsonResponse
    {
        abort_unless($contact->customer_id === $customer->id, 404);
        $data = $this->contactData($request);

        DB::transaction(function () use ($customer, $contact, $data) {
            if (! empty($data['is_primary'])) {
                $customer->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
        });

        return response()->json($contact->fresh());
    }

    public function destroyContact(Customer $customer, Contact $contact): JsonResponse
    {
        abort_unless($contact->customer_id === $customer->id, 404);
        $contact->delete();

        return response()->json(['message' => 'Ansprechpartner gelöscht.']);
    }

    private function contactData(Request $request): array
    {
        return $request->validate([
            'salutation' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    // ---------- Zusatzadressen ----------

    public function storeAddress(Request $request, Customer $customer): JsonResponse
    {
        return response()->json($customer->addresses()->create($this->addressData($request))->fresh(), 201);
    }

    public function updateAddress(Request $request, Customer $customer, Address $address): JsonResponse
    {
        abort_unless($address->customer_id === $customer->id, 404);
        $address->update($this->addressData($request));

        return response()->json($address->fresh());
    }

    public function destroyAddress(Customer $customer, Address $address): JsonResponse
    {
        abort_unless($address->customer_id === $customer->id, 404);
        $address->delete();

        return response()->json(['message' => 'Adresse gelöscht.']);
    }

    private function addressData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:abholung,lieferung,sonstige'],
            'label' => ['nullable', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:10'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
