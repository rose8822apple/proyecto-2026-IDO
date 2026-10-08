<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        $roles = DB::table('roles')
            ->leftJoin('people', 'people.role', '=', 'roles.nombre')
            ->select('roles.id', 'roles.nombre')
            ->selectRaw('COUNT(people.id) AS people_count')
            ->groupBy('roles.id', 'roles.nombre')
            ->orderBy('roles.nombre')
            ->get();

        return view('roles.index', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre', ''))]);
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:50', 'not_regex:/[0-9]/', Rule::unique('roles', 'nombre')],
        ], [
            'nombre.required' => 'Escribe el nombre del rol.',
            'nombre.not_regex' => 'El nombre del rol no puede contener números.',
            'nombre.unique' => 'Ese rol ya existe.',
        ]);

        DB::table('roles')->insert(['nombre' => $validated['nombre']]);

        return redirect()->route('roles')->with('success', 'Rol agregado; ya está disponible en el formulario de personal.');
    }

    public function destroy(int $role)
    {
        DB::transaction(function () use ($role): void {
            $record = DB::table('roles')->where('id', $role)->lockForUpdate()->first();
            abort_if($record === null, 404);

            if (DB::table('people')->where('role', $record->nombre)->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'No se puede eliminar este rol porque está asignado a una o más personas.',
                ]);
            }

            DB::table('roles')->where('id', $role)->delete();
        });

        return redirect()->route('roles')->with('success', 'Rol eliminado de las opciones disponibles.');
    }
}
