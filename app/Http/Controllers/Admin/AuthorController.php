<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuthorRequest;
use App\Models\Author;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AuthorController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Authors/Index', [
            'authors' => Author::withCount('books')->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Authors/Create');
    }

    public function store(AuthorRequest $request): RedirectResponse
    {
        Author::create($request->validated());

        return redirect()->route('admin.authors.index')->with('success', 'Autor je uspešno dodat.');
    }

    public function edit(Author $author): Response
    {
        return Inertia::render('Admin/Authors/Edit', ['author' => $author]);
    }

    public function update(AuthorRequest $request, Author $author): RedirectResponse
    {
        $author->update($request->validated());

        return redirect()->route('admin.authors.index')->with('success', 'Autor je uspešno izmenjen.');
    }

    public function destroy(Author $author): RedirectResponse
    {
        // FK author_book.author_id je restrict — bez ove provere bi brisanje završilo kao 500.
        if ($author->books()->exists()) {
            return redirect()->route('admin.authors.index')
                ->with('error', 'Autor ima povezane knjige i ne može se obrisati. Prvo ga ukloni sa knjiga.');
        }

        $author->delete();

        return redirect()->route('admin.authors.index')->with('success', 'Autor je obrisan.');
    }
}
