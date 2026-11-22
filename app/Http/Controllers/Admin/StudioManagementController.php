<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudioManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_cinema' => [
                'required', 'integer',
                Rule::exists('cinemas', 'id_cinema')->where('is_active', true),
            ],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('studios', 'name')->where('id_cinema', (int) $request->input('id_cinema')),
            ],
            'seat_capacity' => ['required', 'integer', 'between:1,300'],
            'turnaround_minutes' => ['required', 'integer', 'between:5,90'],
        ]);

        Studio::query()->create($data);

        return to_route('admin.cinemas.index')->with('success', 'Studio berhasil ditambahkan.');
    }

    public function update(Request $request, Studio $studio): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $studio->update($data);

        return back()->with('success', 'Status studio berhasil diperbarui.');
    }
}
