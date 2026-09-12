import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../app/providers/bootstrap_provider.dart';
import '../app/providers/auth_provider.dart';
import '../app/providers/cart_provider.dart';
import '../app/providers/notification_provider.dart';
import '../app/providers/driver_provider.dart';
import '../config/app_config.dart';
import '../services/notification_service.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap({bool force = false}) async {
    try {
      // Initialize notification service safely
      await NotificationService().initialize();
    } catch (e) {
      debugPrint('NotificationService initialization failed: $e');
    }

    if (!mounted) return;

    try {
      // Load tenant data
      final bootstrap = context.read<BootstrapProvider>();
      if (!bootstrap.isLoaded || force) {
        await bootstrap.loadTenant(force: force);
      }
    } catch (e) {
      debugPrint('Bootstrap loading failed: $e');
    }

    if (!mounted) return;

    // A customer/driver token is tenant-scoped. Never proceed into a login
    // flow when store bootstrap failed, because every subsequent request would
    // be rejected and the customer would receive misleading errors.
    if (context.read<BootstrapProvider>().error != null) {
      return;
    }

    try {
      if (AppConfig.appMode == 'marketplace') {
        if (mounted) context.go('/marketplace');
      } else if (AppConfig.appMode == 'driver') {
        // Driver mode
        final driver = context.read<DriverProvider>();
        await driver.loadSavedToken();
        if (mounted) {
          context.go(driver.isAuthenticated ? '/driver/home' : '/driver/login');
        }
      } else {
        // Customer mode
        final auth = context.read<AuthProvider>();
        await auth.loadSavedToken();

        if (mounted) {
          if (auth.isAuthenticated) {
            context.read<CartProvider>().loadCart();
            context.read<NotificationProvider>().startPolling();
            context.go('/home');
          } else {
            context.go('/login');
          }
        }
      }
    } catch (e) {
      debugPrint('Bootstrap navigation error: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final bootstrap = context.watch<BootstrapProvider>();

    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (bootstrap.logoUrl != null &&
                bootstrap.logoUrl!.isNotEmpty &&
                AppConfig.appMode != 'driver')
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: Image.network(
                  AppConfig.assetUrl(bootstrap.logoUrl!),
                  width: 100,
                  height: 100,
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => Icon(
                    Icons.storefront,
                    size: 80,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                ),
              )
            else
              Icon(
                AppConfig.appMode == 'driver'
                    ? Icons.delivery_dining
                    : Icons.storefront,
                size: 80,
                color: Theme.of(context).colorScheme.primary,
              ),
            const SizedBox(height: 16),
            Text(
              bootstrap.tenantName ?? AppConfig.appName,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 24),
            if (bootstrap.error != null) ...[
              Text(
                bootstrap.error!,
                style: const TextStyle(color: Colors.red),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () => _bootstrap(force: true),
                child: const Text('Retry'),
              ),
            ] else
              const CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
