<?php

declare(strict_types=1);

namespace App\Modules\Platform\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Database\Connection;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\CapabilityRepository;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Auth\Domain\Role;
use App\Modules\Tenant\Service\AppTokenService;
use Ramsey\Uuid\Uuid;

final class PlatformAdminController
{
    public function __construct(
        private readonly Connection $db,
        private readonly TenantRepository $tenantRepo,
        private readonly CapabilityRepository $capabilityRepo,
        private readonly AdminRepository $adminRepo,
        private readonly PasswordService $passwordService,
        private readonly AppTokenService $appTokenService,
    ) {
    }

    // ── Tenants ──

    public function listTenants(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $status = $request->input('status');
        $tenants = $this->tenantRepo->findAll($status);

        $result = [];
        foreach ($tenants as $tenant) {
            $t = $tenant->toPublicArray();
            $t['status'] = $tenant->status;
            $t['contact_email'] = $tenant->contactEmail;
            $t['contact_phone'] = $tenant->contactPhone;
            $t['timezone'] = $tenant->timezone;
            $t['currency'] = $tenant->currency;
            $t['created_at'] = $tenant->createdAt;
            $commercial = $this->db->fetchOne('SELECT commercial_plan, marketplace_status, marketplace_sort_order FROM tenants WHERE id = ?', [$tenant->id]);
            $t['commercial_plan'] = $commercial['commercial_plan'] ?? 'branded';
            $t['marketplace_status'] = $commercial['marketplace_status'] ?? 'hidden';
            $t['marketplace_sort_order'] = (int) ($commercial['marketplace_sort_order'] ?? 0);

            // Get capabilities
            $t['capabilities'] = $this->capabilityRepo->getForTenant($tenant->id);

            // Get admin count
            $row = $this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM admins WHERE tenant_id = ? AND deleted_at IS NULL",
                [$tenant->id]
            );
            $t['admin_count'] = (int) $row['cnt'];

            // Get order/revenue stats
            $stats = $this->db->fetchOne(
                "SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as total_revenue FROM orders WHERE tenant_id = ?",
                [$tenant->id]
            );
            $t['order_count'] = (int) ($stats['order_count'] ?? 0);
            $t['total_revenue'] = (int) ($stats['total_revenue'] ?? 0);

            $result[] = $t;
        }

        return Response::success($result);
    }

    public function getTenant(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $t = $tenant->toPublicArray();
        $t['status'] = $tenant->status;
        $t['contact_email'] = $tenant->contactEmail;
        $t['contact_phone'] = $tenant->contactPhone;
        $t['address'] = $tenant->address;
        $t['timezone'] = $tenant->timezone;
        $t['currency'] = $tenant->currency;
        $t['locale'] = $tenant->locale;
        $t['configuration'] = $tenant->configuration;
        $t['created_at'] = $tenant->createdAt;
        $t['capabilities'] = $this->capabilityRepo->getForTenant($tenant->id);
        $commercial = $this->db->fetchOne('SELECT commercial_plan, marketplace_status, marketplace_sort_order FROM tenants WHERE id = ?', [$tenant->id]);
        $t['commercial_plan'] = $commercial['commercial_plan'] ?? 'branded';
        $t['marketplace_status'] = $commercial['marketplace_status'] ?? 'hidden';
        $t['marketplace_sort_order'] = (int) ($commercial['marketplace_sort_order'] ?? 0);

        // Get admins for this tenant
        $t['admins'] = $this->adminRepo->findByTenant($tenant->id);

        // Get active app token prefix
        $tokenRow = $this->db->fetchOne(
            "SELECT token_prefix FROM app_tokens WHERE tenant_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$tenant->id]
        );
        $t['app_token_prefix'] = $tokenRow['token_prefix'] ?? null;

        return Response::success($t);
    }

    public function createTenant(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $data = $request->json();
        $validator = new Validator();

        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => ['required', 'string', 'min:2', 'max:100'],
            'business_type' => ['required', 'string'],
            'contact_email' => ['required', 'email'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        // Check slug uniqueness
        $existing = $this->tenantRepo->findBySlug($data['slug']);
        if ($existing !== null) {
            return Response::validationError(['slug' => ['This slug is already taken.']]);
        }

        $commercialPlan = $data['commercial_plan'] ?? 'branded';
        $marketplaceStatus = $data['marketplace_status'] ?? 'hidden';
        if (!in_array($commercialPlan, ['branded', 'marketplace'], true)) {
            return Response::validationError(['commercial_plan' => ['Must be branded or marketplace.']]);
        }
        if (!in_array($marketplaceStatus, ['hidden', 'pending', 'active', 'paused'], true)) {
            return Response::validationError(['marketplace_status' => ['Invalid marketplace status.']]);
        }

        $tenant = $this->tenantRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'business_type' => $data['business_type'],
            'status' => $data['status'] ?? 'active',
            'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
            'currency' => $data['currency'] ?? 'INR',
            'locale' => $data['locale'] ?? 'en-IN',
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'],
            'address' => $data['address'] ?? null,
        ]);

        $this->db->execute(
            'UPDATE tenants SET commercial_plan = ?, marketplace_status = ?, marketplace_sort_order = ? WHERE id = ?',
            [$commercialPlan, $marketplaceStatus, (int) ($data['marketplace_sort_order'] ?? 0), $tenant->id],
        );

        // Set default capabilities
        $defaultCapabilities = [
            'orders' => true,
            'delivery' => true,
            'pickup' => true,
            'coupons' => true,
            'promotions' => true,
            'bundles' => true,
            'drivers' => true,
            'payments_online' => false,
            'loyalty' => false,
            'reviews' => false,
        ];
        $this->capabilityRepo->setMany($tenant->id, $defaultCapabilities);

        // Generate app token
        $tokenResult = $this->appTokenService->generate($tenant->id);

        // Create owner admin if email+password provided
        $ownerInfo = null;
        if (!empty($data['owner_name']) && !empty($data['owner_email']) && !empty($data['owner_password'])) {
            $adminId = $this->adminRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_id' => $tenant->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password_hash' => $this->passwordService->hash($data['owner_password']),
                'role' => Role::TENANT_OWNER,
                'status' => 'active',
            ]);
            $ownerInfo = ['name' => $data['owner_name'], 'email' => $data['owner_email']];
        }

        return Response::success([
            'tenant' => $tenant->toPublicArray(),
            'app_token' => $tokenResult['plain_token'] ?? null,
            'owner' => $ownerInfo,
        ], status: 201);
    }

    public function updateTenant(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $data = $request->json();
        $fields = [];
        $allowed = ['name', 'status', 'business_type', 'contact_phone', 'contact_email',
                     'address', 'timezone', 'currency', 'locale', 'commercial_plan',
                     'marketplace_status', 'marketplace_sort_order'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }

        if (isset($fields['commercial_plan']) && !in_array($fields['commercial_plan'], ['branded', 'marketplace'], true)) {
            return Response::validationError(['commercial_plan' => ['Must be branded or marketplace.']]);
        }
        if (isset($fields['marketplace_status']) && !in_array($fields['marketplace_status'], ['hidden', 'pending', 'active', 'paused'], true)) {
            return Response::validationError(['marketplace_status' => ['Invalid marketplace status.']]);
        }
        if (isset($fields['marketplace_sort_order']) && (!is_numeric($fields['marketplace_sort_order']) || (int) $fields['marketplace_sort_order'] < 0)) {
            return Response::validationError(['marketplace_sort_order' => ['Must be a positive whole number.']]);
        }

        if (!empty($fields)) {
            $this->tenantRepo->update($tenant->id, $fields);
        }

        $updated = $this->tenantRepo->findByUuid($params['uuid']);
        $result = $updated->toPublicArray();
        $result['status'] = $updated->status;
        $result['contact_email'] = $updated->contactEmail;
        $result['contact_phone'] = $updated->contactPhone;
        $commercial = $this->db->fetchOne('SELECT commercial_plan, marketplace_status, marketplace_sort_order FROM tenants WHERE id = ?', [$updated->id]);
        $result['commercial_plan'] = $commercial['commercial_plan'] ?? 'branded';
        $result['marketplace_status'] = $commercial['marketplace_status'] ?? 'hidden';
        $result['marketplace_sort_order'] = (int) ($commercial['marketplace_sort_order'] ?? 0);

        return Response::success($result);
    }

    // ── Capabilities / Features ──

    public function updateCapabilities(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $data = $request->json();
        $capabilities = $data['capabilities'] ?? [];

        if (!is_array($capabilities)) {
            return Response::validationError(['capabilities' => ['Must be an object of capability: boolean pairs.']]);
        }

        $this->capabilityRepo->setMany($tenant->id, $capabilities);

        return Response::success([
            'capabilities' => $this->capabilityRepo->getForTenant($tenant->id),
        ]);
    }

    // ── Tenant Admins ──

    public function listTenantAdmins(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $admins = $this->adminRepo->findByTenant($tenant->id);

        return Response::success($admins);
    }

    public function createTenantAdmin(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        $data = $request->json();
        $validator = new Validator();

        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        // Check email uniqueness within tenant
        $existing = $this->adminRepo->findByEmail($data['email'], $tenant->id);
        if ($existing !== null) {
            return Response::validationError(['email' => ['This email is already registered for this tenant.']]);
        }

        $adminId = $this->adminRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $this->passwordService->hash($data['password']),
            'role' => $data['role'],
            'status' => 'active',
        ]);

        $admin = $this->adminRepo->findById($adminId);

        return Response::success([
            'uuid' => $admin['uuid'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $admin['role'],
            'status' => $admin['status'],
        ], status: 201);
    }

    // ── Platform Stats ──

    public function dashboard(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenantCount = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM tenants WHERE deleted_at IS NULL");
        $activeTenants = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM tenants WHERE status = 'active' AND deleted_at IS NULL");
        $totalOrders = $this->db->fetchOne("SELECT COUNT(*) as cnt, COALESCE(SUM(total), 0) as revenue FROM orders");
        $totalCustomers = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM customers WHERE deleted_at IS NULL");
        $totalAdmins = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM admins WHERE deleted_at IS NULL AND tenant_id IS NOT NULL");
        $marketplaceTenants = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM tenants WHERE commercial_plan = 'marketplace' AND deleted_at IS NULL");
        $marketplaceActive = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM tenants WHERE commercial_plan = 'marketplace' AND marketplace_status = 'active' AND deleted_at IS NULL");
        $fees = $this->db->fetchOne("SELECT
            COALESCE(SUM(CASE WHEN status = 'accrued' THEN fee_amount ELSE 0 END), 0) AS accrued,
            COALESCE(SUM(CASE WHEN status = 'settled' THEN fee_amount ELSE 0 END), 0) AS settled
            FROM platform_fee_ledger");

        return Response::success([
            'total_tenants' => (int) $tenantCount['cnt'],
            'active_tenants' => (int) $activeTenants['cnt'],
            'total_orders' => (int) $totalOrders['cnt'],
            'total_revenue' => (int) $totalOrders['revenue'],
            'total_customers' => (int) $totalCustomers['cnt'],
            'total_tenant_admins' => (int) $totalAdmins['cnt'],
            'marketplace_tenants' => (int) ($marketplaceTenants['cnt'] ?? 0),
            'marketplace_active_tenants' => (int) ($marketplaceActive['cnt'] ?? 0),
            'platform_fee_accrued' => (int) ($fees['accrued'] ?? 0),
            'platform_fee_settled' => (int) ($fees['settled'] ?? 0),
        ]);
    }

    public function listFeeLedger(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);
        $status = $request->input('status');
        $sql = "SELECT l.uuid, l.gross_order_value, l.fee_amount, l.fee_rule, l.status, l.settled_at, l.created_at,
                       t.uuid AS tenant_uuid, t.name AS tenant_name, o.order_number
                FROM platform_fee_ledger l
                JOIN tenants t ON t.id = l.tenant_id
                JOIN orders o ON o.id = l.order_id";
        $values = [];
        if (in_array($status, ['accrued', 'settled'], true)) {
            $sql .= ' WHERE l.status = ?';
            $values[] = $status;
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT 200';
        return Response::success($this->db->fetchAll($sql, $values));
    }

    public function settleFeeLedger(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);
        $updated = $this->db->execute(
            "UPDATE platform_fee_ledger SET status = 'settled', settled_at = NOW() WHERE uuid = ? AND status = 'accrued'",
            [$params['uuid']],
        );
        if ($updated === 0) return Response::notFound('Accrued fee entry not found.');
        return Response::success(['settled' => true]);
    }

    // ── App Token ──

    public function regenerateToken(Request $request, array $params): Response
    {
        $this->requirePlatformAdmin($request);

        $tenant = $this->tenantRepo->findByUuid($params['uuid']);
        if ($tenant === null) {
            return Response::notFound('Tenant not found.');
        }

        // Revoke all existing tokens
        $this->appTokenService->revokeAllForTenant($tenant->id);

        // Generate new token
        $tokenResult = $this->appTokenService->generate($tenant->id);

        return Response::success([
            'app_token' => $tokenResult['token'],
            'prefix' => $tokenResult['prefix'],
        ]);
    }

    // ── Guard ──

    private function requirePlatformAdmin(Request $request): void
    {
        $role = $request->authClaims['role'] ?? '';
        if ($role !== Role::PLATFORM_ADMIN) {
            throw new \RuntimeException('Platform admin access required.');
        }
    }
}
