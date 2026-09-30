<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends ContactController
{
    public const MODULE = 'customers';

    protected string $type = 'customer';

    public function show(Customer $customer)
    {
        return $this->showContact($customer);
    }

    public function edit(Customer $customer)
    {
        return $this->editContact($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        return $this->updateContact($request, $customer);
    }

    public function destroy(Customer $customer)
    {
        return $this->destroyContact($customer);
    }

    public function statement(Request $request, Customer $customer)
    {
        return $this->statementFor($request, $customer);
    }
}
