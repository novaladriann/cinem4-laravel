<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CinemaManagementController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/cinemas/Index', [
            'cinemas' => Cinema::query()->with('studios')->orderBy('name')->get(),
            'success' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        Cinema::query()->create($data);

        return to_route('admin.cinemas.index')->with('success', 'Bioskop berhasil ditambahkan.');
    }

    public function update(Request $request, Cinema $cinema): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $cinema->update($data);

        return back()->with('success', 'Status bioskop berhasil diperbarui.');
    }
}
