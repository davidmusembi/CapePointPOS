<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends ContactController
{
    public const MODULE = 'suppliers';

    protected string $type = 'supplier';

    public function show(Supplier $supplier)
    {
        return $this->showContact($supplier);
    }

    public function edit(Supplier $supplier)
    {
        return $this->editContact($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        return $this->updateContact($request, $supplier);
    }

    public function destroy(Supplier $supplier)
    {
        return $this->destroyContact($supplier);
    }

    public function statement(Request $request, Supplier $supplier)
    {
        return $this->statementFor($request, $supplier);
    }
}
