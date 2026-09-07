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

  Future<void> _bootstrap() async {
    // Initialize notification service
    await NotificationService().initialize();

    // Load tenant data
    final bootstrap = context.read<BootstrapProvider>();
    await bootstrap.loadTenant();

    if (!mounted) return;

    if (AppConfig.appMode == 'driver') {
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
        }
        context.go('/home');
      }
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
            Icon(
              AppConfig.appMode == 'driver' ? Icons.delivery_dining : Icons.storefront,
              size: 80,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 16),
            Text(
              bootstrap.tenantName ?? 'CloudStore',
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
                onPressed: _bootstrap,
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
