<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index()
    {
        $keys = ApiKey::with('creator')->latest()->paginate(20);

        return view('admin.api_keys.index', compact('keys'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        [$record, $plain] = ApiKey::generate($validated['name'], auth()->id());

        return redirect()
            ->route('admin.api_keys.index')
            ->with('success', 'API key berhasil dibuat.')
            ->with('plain_key', $plain)
            ->with('plain_key_id', $record->id);
    }

    /**
     * Revoke (never hard-delete: keeps an audit trail of past access).
     */
    public function destroy(ApiKey $apiKey)
    {
        if ($apiKey->revoked_at === null) {
            $apiKey->update(['revoked_at' => now()]);
        }

        return redirect()->route('admin.api_keys.index')->with('success', 'API key dicabut.');
    }
}
