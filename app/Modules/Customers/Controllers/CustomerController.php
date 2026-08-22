<?php

namespace Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Customers\Models\Customer;
use Modules\Customers\Requests\StoreCustomerRequest;
use Modules\Customers\Requests\UpdateCustomerRequest;
use Modules\Customers\Resources\CustomerResource;
use Modules\Customers\Services\CustomerService;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $customers = $this->service->list(
            filters: $request->only(['is_active', 'company']),
            search: $request->get('search'),
            sortBy: $request->get('sort_by'),
            direction: $request->get('direction', 'desc'),
            perPage: $request->integer('per_page', 15),
        );

        return CustomerResource::collection($customers)->response();
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->service->create($request->validated());

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $uuid): JsonResponse
    {
        $customer = $this->service->findByUuid($uuid);

        Gate::authorize('view', $customer);

        return (new CustomerResource($customer->loadCount(['projects', 'issues'])))->response();
    }

    public function update(UpdateCustomerRequest $request, string $uuid): JsonResponse
    {
        $customer = $this->service->update($uuid, $request->validated());

        return (new CustomerResource($customer))->response();
    }

    public function destroy(string $uuid): JsonResponse
    {
        Gate::authorize('delete', $this->service->findByUuid($uuid));

        $this->service->delete($uuid);

        return response()->json(['message' => 'Customer deleted successfully']);
    }
}
