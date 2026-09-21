<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PublisherRequest;
use App\Models\Publisher;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PublisherController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Publishers/Index', [
            'publishers' => Publisher::withCount('books')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Publishers/Create');
    }

    public function store(PublisherRequest $request): RedirectResponse
    {
        Publisher::create($request->validated());

        return redirect()->route('admin.publishers.index')->with('success', 'Izdavač je uspešno dodat.');
    }

    public function edit(Publisher $publisher): Response
    {
        return Inertia::render('Admin/Publishers/Edit', ['publisher' => $publisher]);
    }

    public function update(PublisherRequest $request, Publisher $publisher): RedirectResponse
    {
        $publisher->update($request->validated());

        return redirect()->route('admin.publishers.index')->with('success', 'Izdavač je uspešno izmenjen.');
    }

    public function destroy(Publisher $publisher): RedirectResponse
    {
        // FK books.publisher_id je nullOnDelete: knjige ostaju, samo bez izdavača.
        $publisher->delete();

        return redirect()->route('admin.publishers.index')->with('success', 'Izdavač je obrisan.');
    }
}
