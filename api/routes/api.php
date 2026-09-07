<?php

declare(strict_types=1);

use App\Core\Http\Router;
use App\Http\Controllers\HealthController;
use App\Modules\Tenant\Controller\BootstrapController;
use App\Modules\Auth\Controller\AdminAuthController;
use App\Modules\Auth\Controller\CustomerAuthController;
use App\Modules\Auth\Controller\DriverAuthController;
use App\Modules\Catalog\Controller\AdminCatalogController;
use App\Modules\Catalog\Controller\PublicCatalogController;
use App\Modules\Customer\Controller\AddressController;
use App\Modules\Cart\Controller\CartController;
use App\Modules\Order\Controller\CheckoutController;
use App\Modules\Order\Controller\OrderController;
use App\Modules\Order\Controller\AdminOrderController;
use App\Modules\Payment\Controller\PaymentController;
use App\Modules\Delivery\Controller\DriverDeliveryController;
use App\Modules\Notification\Controller\NotificationController;
use App\Modules\Notification\Controller\AdminNotificationController;
use App\Modules\Admin\Controller\AdminSettingsController;
use App\Modules\Admin\Controller\AdminDriverController;
use App\Core\Http\Middleware\TenantMiddleware;

return function (Router $router): void {
    // Health
    $router->get('/health', [HealthController::class, 'index']);
    $router->get('/health/ready', [HealthController::class, 'ready']);
    $router->get('/health/live', [HealthController::class, 'live']);

    // API v1
    $router->group('/api/v1', [], function (Router $router) {
        // Bootstrap
        $router->post('/app/bootstrap', [BootstrapController::class, 'bootstrap']);

        // Admin auth
        $router->post('/admin/login', [AdminAuthController::class, 'login']);
        $router->get('/admin/me', [AdminAuthController::class, 'me'], ['middleware.auth.admin']);
        $router->post('/admin/change-password', [AdminAuthController::class, 'changePassword'], ['middleware.auth.admin']);

        // Admin panel (authenticated)
        $router->group('/admin', ['middleware.auth.admin'], function (Router $router) {
            // Dashboard
            $router->get('/dashboard', [AdminOrderController::class, 'dashboard']);

            // Catalog
            $router->get('/categories', [AdminCatalogController::class, 'listCategories']);
            $router->post('/categories', [AdminCatalogController::class, 'createCategory']);
            $router->put('/categories/{uuid}', [AdminCatalogController::class, 'updateCategory']);
            $router->delete('/categories/{uuid}', [AdminCatalogController::class, 'deleteCategory']);

            $router->get('/products', [AdminCatalogController::class, 'listProducts']);
            $router->post('/products', [AdminCatalogController::class, 'createProduct']);
            $router->get('/products/{uuid}', [AdminCatalogController::class, 'getProduct']);
            $router->put('/products/{uuid}', [AdminCatalogController::class, 'updateProduct']);
            $router->delete('/products/{uuid}', [AdminCatalogController::class, 'deleteProduct']);

            // Product images
            $router->get('/products/{uuid}/images', [AdminCatalogController::class, 'listProductImages']);
            $router->post('/products/{uuid}/images', [AdminCatalogController::class, 'uploadProductImage']);
            $router->delete('/products/{uuid}/images/{imageId}', [AdminCatalogController::class, 'deleteProductImage']);
            $router->patch('/products/{uuid}/images/{imageId}/primary', [AdminCatalogController::class, 'setPrimaryImage']);

            // Product variants
            $router->post('/products/{uuid}/variants', [AdminCatalogController::class, 'createVariant']);
            $router->put('/products/{uuid}/variants/{variantId}', [AdminCatalogController::class, 'updateVariant']);
            $router->delete('/products/{uuid}/variants/{variantId}', [AdminCatalogController::class, 'deleteVariant']);

            // Product addons
            $router->post('/products/{uuid}/addons', [AdminCatalogController::class, 'attachAddonToProduct']);
            $router->delete('/products/{uuid}/addons/{groupId}', [AdminCatalogController::class, 'detachAddonFromProduct']);

            // Addon groups
            $router->get('/addon-groups', [AdminCatalogController::class, 'listAddonGroups']);
            $router->post('/addon-groups', [AdminCatalogController::class, 'createAddonGroup']);
            $router->put('/addon-groups/{groupId}', [AdminCatalogController::class, 'updateAddonGroup']);
            $router->post('/addon-groups/{groupId}/items', [AdminCatalogController::class, 'addAddonItem']);
            $router->delete('/addon-groups/{groupId}/items/{itemId}', [AdminCatalogController::class, 'deleteAddonItem']);

            // Orders
            $router->get('/orders', [AdminOrderController::class, 'list']);
            $router->get('/orders/board', [AdminOrderController::class, 'board']);
            $router->get('/orders/{uuid}', [AdminOrderController::class, 'show']);
            $router->patch('/orders/{uuid}/status', [AdminOrderController::class, 'updateStatus']);
            $router->post('/orders/{uuid}/assign-driver', [AdminOrderController::class, 'assignDriver']);
            $router->post('/orders/{uuid}/refund', [AdminOrderController::class, 'refund']);

            // Drivers
            $router->get('/drivers/available', [AdminOrderController::class, 'availableDrivers']);
            $router->get('/drivers', [AdminDriverController::class, 'list']);
            $router->post('/drivers', [AdminDriverController::class, 'create']);
            $router->put('/drivers/{uuid}', [AdminDriverController::class, 'update']);
            $router->delete('/drivers/{uuid}', [AdminDriverController::class, 'delete']);

            // Notifications
            $router->get('/notifications', [AdminNotificationController::class, 'list']);
            $router->patch('/notifications/{id}/read', [AdminNotificationController::class, 'markRead']);
            $router->post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead']);

            // Delivery zones
            $router->get('/delivery-zones', [AdminSettingsController::class, 'listZones']);
            $router->post('/delivery-zones', [AdminSettingsController::class, 'createZone']);
            $router->put('/delivery-zones/{zoneId}', [AdminSettingsController::class, 'updateZone']);
            $router->delete('/delivery-zones/{zoneId}', [AdminSettingsController::class, 'deleteZone']);

            // Customers
            $router->get('/customers', [AdminSettingsController::class, 'listCustomers']);
            $router->get('/customers/{uuid}', [AdminSettingsController::class, 'getCustomer']);

            // Store settings
            $router->get('/settings', [AdminSettingsController::class, 'getSettings']);
            $router->put('/settings', [AdminSettingsController::class, 'updateSettings']);

            // Enhanced dashboard
            $router->get('/dashboard/enhanced', [AdminSettingsController::class, 'dashboardEnhanced']);
        });

        // Payment webhook (no auth — verified by signature)
        $router->post('/webhooks/payment', [PaymentController::class, 'webhook']);

        // Public/customer routes (tenant-scoped via X-App-Token)
        $router->group('', [TenantMiddleware::class], function (Router $router) {
            // Auth
            $router->post('/customer/otp/request', [CustomerAuthController::class, 'requestOtp']);
            $router->post('/customer/otp/verify', [CustomerAuthController::class, 'verifyOtp']);
            $router->get('/customer/me', [CustomerAuthController::class, 'me'], ['middleware.auth.customer']);
            $router->post('/driver/login', [DriverAuthController::class, 'login']);
            $router->get('/driver/me', [DriverAuthController::class, 'me'], ['middleware.auth.driver']);

            // Public catalog
            $router->get('/catalog', [PublicCatalogController::class, 'catalog']);
            $router->get('/categories', [PublicCatalogController::class, 'categories']);
            $router->get('/categories/{uuid}/products', [PublicCatalogController::class, 'productsByCategory']);
            $router->get('/products/{uuid}', [PublicCatalogController::class, 'product']);

            // Customer (authenticated)
            $router->group('/customer', ['middleware.auth.customer'], function (Router $router) {
                // Addresses
                $router->get('/addresses', [AddressController::class, 'list']);
                $router->post('/addresses', [AddressController::class, 'create']);
                $router->put('/addresses/{uuid}', [AddressController::class, 'update']);
                $router->delete('/addresses/{uuid}', [AddressController::class, 'delete']);

                // Cart
                $router->get('/cart', [CartController::class, 'get']);
                $router->post('/cart/items', [CartController::class, 'addItem']);
                $router->patch('/cart/items/{itemId}', [CartController::class, 'updateItem']);
                $router->delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
                $router->delete('/cart', [CartController::class, 'clear']);

                // Checkout & Orders
                $router->post('/checkout', [CheckoutController::class, 'createOrder']);
                $router->get('/orders', [OrderController::class, 'list']);
                $router->get('/orders/{uuid}', [OrderController::class, 'show']);

                // Payments
                $router->post('/payments/initiate', [PaymentController::class, 'initiate']);

                // Notifications
                $router->get('/notifications', [NotificationController::class, 'listCustomer']);
                $router->patch('/notifications/{id}/read', [NotificationController::class, 'markRead']);
                $router->post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
            });

            // Driver (authenticated)
            $router->group('/driver', ['middleware.auth.driver'], function (Router $router) {
                $router->get('/deliveries', [DriverDeliveryController::class, 'myDeliveries']);
                $router->patch('/deliveries/{assignmentId}/status', [DriverDeliveryController::class, 'updateStatus']);
                $router->post('/location', [DriverDeliveryController::class, 'updateLocation']);
                $router->post('/availability', [DriverDeliveryController::class, 'setAvailability']);
            });
        });
    });
};
