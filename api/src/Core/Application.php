<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Router;
use App\Core\Database\Connection;
use App\Core\Config\Config;
use App\Core\Logging\Logger;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\AppTokenRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use App\Modules\Tenant\Repository\CapabilityRepository;
use App\Modules\Tenant\Service\TenantService;
use App\Modules\Tenant\Service\AppTokenService;
use App\Modules\Tenant\Controller\BootstrapController;
use App\Modules\Auth\Service\JwtService;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Auth\Service\OtpService;
use App\Modules\Auth\Service\OtpDeliveryService;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Auth\Controller\AdminAuthController;
use App\Modules\Auth\Controller\CustomerAuthController;
use App\Modules\Auth\Controller\DriverAuthController;
use App\Core\Http\Middleware\AuthMiddleware;
use App\Core\Http\Middleware\TenantMiddleware;
use App\Modules\Catalog\Repository\CategoryRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Catalog\Repository\AddonRepository;
use App\Modules\Catalog\Service\CatalogService;
use App\Modules\Catalog\Controller\AdminCatalogController;
use App\Modules\Catalog\Controller\PublicCatalogController;
use App\Modules\Customer\Repository\AddressRepository;
use App\Modules\Customer\Controller\AddressController;
use App\Modules\Delivery\Repository\DeliveryZoneRepository;
use App\Modules\Delivery\Service\DeliveryFeeService;
use App\Modules\Cart\Repository\CartRepository;
use App\Modules\Cart\Controller\CartController;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Order\Service\IdempotencyService;
use App\Modules\Order\Service\CheckoutService;
use App\Modules\Order\Controller\CheckoutController;
use App\Modules\Order\Controller\OrderController;
use App\Modules\Order\Controller\AdminOrderController;
use App\Modules\Order\Controller\AdminPosController;
use App\Modules\Order\Service\OrderManagementService;
use App\Modules\Payment\Repository\PaymentRepository;
use App\Modules\Payment\Repository\RefundRepository;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Payment\Controller\PaymentController;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Delivery\Service\DriverService;
use App\Modules\Delivery\Controller\DriverDeliveryController;
use App\Modules\Notification\Repository\NotificationRepository;
use App\Modules\Notification\Service\NotificationService;
use App\Modules\Notification\Controller\NotificationController;
use App\Modules\Notification\Controller\AdminNotificationController;
use App\Modules\Admin\Controller\AdminSettingsController;
use App\Modules\Catalog\Service\ImageService;
use App\Modules\Offer\Repository\CouponRepository;
use App\Modules\Offer\Repository\PromotionRepository;
use App\Modules\Offer\Repository\BundleRepository;
use App\Modules\Offer\Service\CouponService;
use App\Modules\Offer\Service\PromotionEngine;
use App\Modules\Offer\Service\DiscountCalculator;
use App\Modules\Offer\Controller\AdminOfferController;
use App\Modules\Offer\Controller\PublicOfferController;
use App\Modules\Platform\Controller\PlatformAdminController;
use App\Modules\Platform\Controller\BuildController;
use App\Modules\Platform\Controller\MarketplaceController;
use App\Core\Http\Middleware\CorsMiddleware;
use App\Core\Http\Middleware\RateLimitMiddleware;

final class Application
{
    private static ?self $instance = null;
    private Container $container;

    private function __construct(
        private readonly string $basePath,
    ) {
        $this->container = new Container();
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
            self::$instance->bootstrap();
        }

        return self::$instance;
    }

    private function bootstrap(): void
    {
        $this->loadEnvironment();
        $this->registerCoreServices();
        $this->registerRoutes();
    }

    private function loadEnvironment(): void
    {
        if (file_exists($this->basePath . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($this->basePath);
            $dotenv->load();
        }
    }

    private function registerCoreServices(): void
    {
        $this->container->singleton(Config::class, fn () => new Config());

        $this->container->singleton(Logger::class, fn () => new Logger(
            $this->basePath . '/storage/logs',
            $this->container->get(Config::class)->get('LOG_LEVEL', 'debug'),
        ));

        $this->container->singleton(Connection::class, fn () => new Connection(
            host: $this->container->get(Config::class)->get('DB_HOST', '127.0.0.1'),
            port: (int) $this->container->get(Config::class)->get('DB_PORT', '3306'),
            database: $this->container->get(Config::class)->get('DB_DATABASE', 'cloudstore'),
            username: $this->container->get(Config::class)->get('DB_USERNAME', 'root'),
            password: $this->container->get(Config::class)->get('DB_PASSWORD', ''),
        ));

        $this->container->singleton(Router::class, fn () => new Router($this->container));

        // Tenant module
        $this->container->singleton(TenantRepository::class, fn () => new TenantRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(AppTokenRepository::class, fn () => new AppTokenRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(BrandingRepository::class, fn () => new BrandingRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(CapabilityRepository::class, fn () => new CapabilityRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(AppTokenService::class, fn () => new AppTokenService(
            $this->container->get(AppTokenRepository::class),
        ));

        $this->container->singleton(TenantService::class, fn () => new TenantService(
            $this->container->get(TenantRepository::class),
            $this->container->get(BrandingRepository::class),
            $this->container->get(CapabilityRepository::class),
        ));

        $this->container->singleton(BootstrapController::class, fn () => new BootstrapController(
            $this->container->get(AppTokenService::class),
            $this->container->get(TenantService::class),
        ));

        // Auth module
        $jwtSecret = $this->container->get(Config::class)->get('JWT_SECRET');
        if (strlen($jwtSecret) < 32) {
            throw new \RuntimeException('JWT_SECRET must be configured with at least 32 characters.');
        }

        $this->container->singleton(JwtService::class, fn () => new JwtService(
            $jwtSecret,
            $this->container->get(Config::class)->getInt('JWT_TTL', 3600),
        ));

        $this->container->singleton(PasswordService::class, fn () => new PasswordService());

        $this->container->singleton(OtpService::class, fn () => new OtpService(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(OtpDeliveryService::class, fn () => new OtpDeliveryService(
            $this->container->get(Config::class),
        ));

        $this->container->singleton(AdminRepository::class, fn () => new AdminRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(CustomerRepository::class, fn () => new CustomerRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(DriverRepository::class, fn () => new DriverRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(AdminAuthController::class, fn () => new AdminAuthController(
            $this->container->get(AdminRepository::class),
            $this->container->get(JwtService::class),
            $this->container->get(PasswordService::class),
        ));

        $this->container->singleton(CustomerAuthController::class, fn () => new CustomerAuthController(
            $this->container->get(CustomerRepository::class),
            $this->container->get(JwtService::class),
            $this->container->get(OtpService::class),
            $this->container->get(OtpDeliveryService::class),
        ));

        $this->container->singleton(DriverAuthController::class, fn () => new DriverAuthController(
            $this->container->get(DriverRepository::class),
            $this->container->get(JwtService::class),
            $this->container->get(PasswordService::class),
        ));

        // Middleware
        $this->container->singleton(TenantMiddleware::class, fn () => new TenantMiddleware(
            $this->container->get(AppTokenService::class),
            $this->container->get(TenantRepository::class),
        ));

        $this->container->singleton('middleware.auth.admin', fn () => new AuthMiddleware(
            $this->container->get(JwtService::class),
            requiredType: 'admin',
            tenantRepo: $this->container->get(TenantRepository::class),
        ));

        $this->container->singleton('middleware.auth.customer', fn () => new AuthMiddleware(
            $this->container->get(JwtService::class),
            requiredType: 'customer',
        ));

        $this->container->singleton('middleware.auth.driver', fn () => new AuthMiddleware(
            $this->container->get(JwtService::class),
            requiredType: 'driver',
        ));

        // Catalog module
        $this->container->singleton(CategoryRepository::class, fn () => new CategoryRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(ProductRepository::class, fn () => new ProductRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(VariantRepository::class, fn () => new VariantRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(AddonRepository::class, fn () => new AddonRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(ImageService::class, fn () => new ImageService(
            $this->container->get(Connection::class),
            $this->basePath,
        ));

        $this->container->singleton(CatalogService::class, fn () => new CatalogService(
            $this->container->get(CategoryRepository::class),
            $this->container->get(ProductRepository::class),
            $this->container->get(VariantRepository::class),
            $this->container->get(AddonRepository::class),
            $this->container->get(ImageService::class),
        ));

        $this->container->singleton(AdminCatalogController::class, fn () => new AdminCatalogController(
            $this->container->get(CatalogService::class),
            $this->container->get(ImageService::class),
        ));

        $this->container->singleton(PublicCatalogController::class, fn () => new PublicCatalogController(
            $this->container->get(CatalogService::class),
        ));

        // Offers module
        $this->container->singleton(CouponRepository::class, fn () => new CouponRepository(
            $this->container->get(Connection::class),
        ));
        $this->container->singleton(PromotionRepository::class, fn () => new PromotionRepository(
            $this->container->get(Connection::class),
        ));
        $this->container->singleton(BundleRepository::class, fn () => new BundleRepository(
            $this->container->get(Connection::class),
        ));
        $this->container->singleton(CouponService::class, fn () => new CouponService(
            $this->container->get(CouponRepository::class),
        ));
        $this->container->singleton(PromotionEngine::class, fn () => new PromotionEngine(
            $this->container->get(PromotionRepository::class),
        ));
        $this->container->singleton(DiscountCalculator::class, fn () => new DiscountCalculator(
            $this->container->get(CouponService::class),
            $this->container->get(PromotionEngine::class),
        ));
        $this->container->singleton(AdminOfferController::class, fn () => new AdminOfferController(
            $this->container->get(Connection::class),
            $this->container->get(CouponRepository::class),
            $this->container->get(CouponService::class),
            $this->container->get(PromotionRepository::class),
            $this->container->get(BundleRepository::class),
        ));
        $this->container->singleton(PublicOfferController::class, fn () => new PublicOfferController(
            $this->container->get(CouponService::class),
            $this->container->get(DiscountCalculator::class),
            $this->container->get(PromotionRepository::class),
            $this->container->get(BundleRepository::class),
            $this->container->get(CartRepository::class),
            $this->container->get(CustomerRepository::class),
        ));

        // Customer / Address module
        $this->container->singleton(AddressRepository::class, fn () => new AddressRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(AddressController::class, fn () => new AddressController(
            $this->container->get(AddressRepository::class),
            $this->container->get(CustomerRepository::class),
        ));

        // Delivery module
        $this->container->singleton(DeliveryZoneRepository::class, fn () => new DeliveryZoneRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(DeliveryFeeService::class, fn () => new DeliveryFeeService(
            $this->container->get(DeliveryZoneRepository::class),
        ));

        // Cart module
        $this->container->singleton(CartRepository::class, fn () => new CartRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(CartController::class, fn () => new CartController(
            $this->container->get(CartRepository::class),
            $this->container->get(ProductRepository::class),
            $this->container->get(VariantRepository::class),
            $this->container->get(CustomerRepository::class),
        ));

        // Order module
        $this->container->singleton(OrderRepository::class, fn () => new OrderRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(IdempotencyService::class, fn () => new IdempotencyService(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(CheckoutService::class, fn () => new CheckoutService(
            $this->container->get(Connection::class),
            $this->container->get(CartRepository::class),
            $this->container->get(ProductRepository::class),
            $this->container->get(VariantRepository::class),
            $this->container->get(AddressRepository::class),
            $this->container->get(OrderRepository::class),
            $this->container->get(DeliveryFeeService::class),
            $this->container->get(DiscountCalculator::class),
            $this->container->get(CouponRepository::class),
        ));

        $this->container->singleton(CheckoutController::class, fn () => new CheckoutController(
            $this->container->get(CheckoutService::class),
            $this->container->get(IdempotencyService::class),
            $this->container->get(CustomerRepository::class),
        ));

        $this->container->singleton(OrderController::class, fn () => new OrderController(
            $this->container->get(OrderRepository::class),
            $this->container->get(CustomerRepository::class),
        ));

        // Payment module
        $this->container->singleton(PaymentRepository::class, fn () => new PaymentRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(RefundRepository::class, fn () => new RefundRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(PaymentService::class, fn () => new PaymentService(
            $this->container->get(Connection::class),
            $this->container->get(PaymentRepository::class),
            $this->container->get(RefundRepository::class),
            $this->container->get(OrderRepository::class),
            $this->container->get(Config::class)->get('PAYMENT_SECRET'),
        ));

        $this->container->singleton(PaymentController::class, fn () => new PaymentController(
            $this->container->get(PaymentService::class),
            $this->container->get(CustomerRepository::class),
        ));

        // Delivery module (driver assignments)
        $this->container->singleton(DriverAssignmentRepository::class, fn () => new DriverAssignmentRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(DriverService::class, fn () => new DriverService(
            $this->container->get(Connection::class),
            $this->container->get(DriverRepository::class),
            $this->container->get(DriverAssignmentRepository::class),
            $this->container->get(OrderRepository::class),
        ));

        $this->container->singleton(DriverDeliveryController::class, fn () => new DriverDeliveryController(
            $this->container->get(DriverService::class),
            $this->container->get(DriverRepository::class),
            $this->container->get(DriverAssignmentRepository::class),
        ));

        // Order management (admin)
        $this->container->singleton(OrderManagementService::class, fn () => new OrderManagementService(
            $this->container->get(Connection::class),
            $this->container->get(OrderRepository::class),
        ));

        $this->container->singleton(AdminOrderController::class, fn () => new AdminOrderController(
            $this->container->get(OrderRepository::class),
            $this->container->get(OrderManagementService::class),
            $this->container->get(AdminRepository::class),
            $this->container->get(DriverRepository::class),
            $this->container->get(DriverService::class),
            $this->container->get(PaymentService::class),
        ));

        $this->container->singleton(AdminPosController::class, fn () => new AdminPosController(
            $this->container->get(Connection::class),
            $this->container->get(ProductRepository::class),
            $this->container->get(CustomerRepository::class),
            $this->container->get(OrderRepository::class),
        ));

        // Notification module
        $this->container->singleton(NotificationRepository::class, fn () => new NotificationRepository(
            $this->container->get(Connection::class),
        ));

        $this->container->singleton(NotificationService::class, fn () => new NotificationService(
            $this->container->get(NotificationRepository::class),
        ));

        $this->container->singleton(NotificationController::class, fn () => new NotificationController(
            $this->container->get(NotificationRepository::class),
            $this->container->get(CustomerRepository::class),
        ));

        $this->container->singleton(AdminNotificationController::class, fn () => new AdminNotificationController(
            $this->container->get(NotificationRepository::class),
            $this->container->get(AdminRepository::class),
        ));

        $this->container->singleton(AdminSettingsController::class, fn () => new AdminSettingsController(
            $this->container->get(Connection::class),
            $this->container->get(DeliveryZoneRepository::class),
            $this->container->get(CustomerRepository::class),
            $this->container->get(OrderRepository::class),
            $this->container->get(TenantRepository::class),
            $this->container->get(BrandingRepository::class),
        ));

        // Platform module
        $this->container->singleton(PlatformAdminController::class, fn () => new PlatformAdminController(
            $this->container->get(Connection::class),
            $this->container->get(TenantRepository::class),
            $this->container->get(CapabilityRepository::class),
            $this->container->get(AdminRepository::class),
            $this->container->get(PasswordService::class),
            $this->container->get(AppTokenService::class),
        ));
        $this->container->singleton(BuildController::class, fn () => new BuildController(
            $this->container->get(Connection::class),
            $this->container->get(TenantRepository::class),
            $this->container->get(Config::class),
        ));
        $this->container->singleton(MarketplaceController::class, fn () => new MarketplaceController(
            $this->container->get(Connection::class),
            $this->container->get(CatalogService::class),
        ));

        // Production middleware
        $this->container->singleton(CorsMiddleware::class, fn () => new CorsMiddleware(
            $this->container->get(Config::class)->get('CORS_ORIGINS', '*'),
        ));

        $this->container->singleton(RateLimitMiddleware::class, fn () => new RateLimitMiddleware(
            $this->container->get(Connection::class),
            (int) $this->container->get(Config::class)->get('RATE_LIMIT_MAX', '120'),
            (int) $this->container->get(Config::class)->get('RATE_LIMIT_WINDOW', '60'),
        ));
    }

    private function registerRoutes(): void
    {
        $router = $this->container->get(Router::class);
        $routeFiles = glob($this->basePath . '/routes/*.php');

        foreach ($routeFiles as $file) {
            (require $file)($router);
        }
    }

    public function handleRequest(): void
    {
        $request = Request::capture();
        $startedAt = microtime(true);

        // CORS handling
        $cors = $this->container->get(CorsMiddleware::class);
        $corsResponse = $cors->handle($request);
        if ($corsResponse !== null) {
            $this->logApiRequest($request, $corsResponse, $startedAt);
            $corsResponse->send();
            return;
        }

        try {
            $router = $this->container->get(Router::class);
            $response = $router->dispatch($request);
        } catch (\Throwable $e) {
            $logger = $this->container->get(Logger::class);
            $logger->error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $debug = $this->container->get(Config::class)->get('APP_DEBUG', 'false') === 'true';

            $errorMessage = $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();

            $response = Response::error(
                message: $errorMessage,
                code: 'INTERNAL_ERROR',
                status: 500,
            );
        }

        // Send CORS headers on actual responses
        CorsMiddleware::sendHeaders($request);

        $this->logApiRequest($request, $response, $startedAt);

        $response->send();
    }

    /**
     * Log API traffic without retaining credentials, OTPs, passwords, or bodies.
     * App-token fingerprints are one-way hashes used only to correlate a build
     * with an authentication result while debugging mobile bootstrap failures.
     */
    private function logApiRequest(Request $request, Response $response, float $startedAt): void
    {
        if (!str_starts_with($request->path, '/api/') ||
            !$this->container->get(Config::class)->getBool('API_REQUEST_LOGGING', false)) {
            return;
        }

        $appToken = $request->header('x-app-token');
        $forwardedFor = explode(',', $request->header('x-forwarded-for'))[0];

        $this->container->get(Logger::class)->info('API request completed', [
            'method' => $request->method,
            'path' => $request->path,
            'status' => $response->getStatus(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'client_ip' => trim($forwardedFor) !== '' ? trim($forwardedFor) : ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => substr($request->header('user-agent'), 0, 180),
            'app_token_fingerprint' => $appToken === '' ? null : substr(hash('sha256', $appToken), 0, 12),
            'has_bearer_token' => $request->bearerToken() !== null,
        ]);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
