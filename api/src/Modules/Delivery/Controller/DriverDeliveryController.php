<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Delivery\Service\DriverService;
use App\Modules\Tenant\Domain\TenantContext;

final class DriverDeliveryController
{
    public function __construct(
        private readonly DriverService $driverService,
        private readonly DriverRepository $driverRepo,
        private readonly DriverAssignmentRepository $assignmentRepo,
    ) {
    }

    public function myDeliveries(Request $request, array $params): Response
    {
        $driver = $this->driverRepo->findByUuid($request->authClaims['sub']);
        if ($driver === null) {
            return Response::unauthorized();
        }

        $assignments = $this->assignmentRepo->findActiveByDriver((int) $driver['id']);

        return Response::success($assignments);
    }

    public function updateStatus(Request $request, array $params): Response
    {
        $driver = $this->driverRepo->findByUuid($request->authClaims['sub']);
        if ($driver === null) {
            return Response::unauthorized();
        }

        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'status' => ['required', 'string', 'in:accepted,picked_up,delivered,cancelled'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $result = $this->driverService->updateAssignmentStatus(
            (int) $driver['id'],
            (int) $params['assignmentId'],
            $data['status'],
        );

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }

        return Response::success($result);
    }

    public function updateLocation(Request $request, array $params): Response
    {
        $driver = $this->driverRepo->findByUuid($request->authClaims['sub']);
        if ($driver === null) {
            return Response::unauthorized();
        }

        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'latitude' => ['required'],
            'longitude' => ['required'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $this->driverService->updateLocation(
            (int) $driver['id'],
            (float) $data['latitude'],
            (float) $data['longitude'],
        );

        return Response::success(['updated' => true]);
    }

    public function setAvailability(Request $request, array $params): Response
    {
        $driver = $this->driverRepo->findByUuid($request->authClaims['sub']);
        if ($driver === null) {
            return Response::unauthorized();
        }

        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'availability' => ['required', 'string', 'in:available,busy,offline'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $this->driverService->setAvailability((int) $driver['id'], $data['availability']);

        return Response::success(['availability' => $data['availability']]);
    }
}
