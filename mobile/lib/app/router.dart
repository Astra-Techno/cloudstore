import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../screens/splash_screen.dart';
import '../screens/home_screen.dart';
import '../screens/auth/login_screen.dart';
import '../screens/auth/otp_verify_screen.dart';
import '../screens/auth/onboarding_screen.dart';
import '../screens/catalog/product_detail_screen.dart';
import '../screens/checkout/checkout_screen.dart';
import '../screens/orders/orders_screen.dart';
import '../screens/orders/order_detail_screen.dart';
import '../screens/address/addresses_screen.dart';
import '../screens/address/add_address_screen.dart';
import '../screens/notifications/notifications_screen.dart';
import '../screens/driver/driver_login_screen.dart';
import '../screens/driver/driver_home_screen.dart';
import '../screens/driver/delivery_detail_screen.dart';
import '../screens/marketplace/marketplace_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  routes: [
    // Splash / bootstrap
    GoRoute(
      path: '/',
      builder: (context, state) => const SplashScreen(),
    ),

    // Customer routes
    GoRoute(
      path: '/home',
      builder: (context, state) => const HomeScreen(),
    ),
    GoRoute(
      path: '/marketplace',
      builder: (context, state) => const MarketplaceScreen(),
    ),
    GoRoute(
      path: '/login',
      builder: (context, state) => const LoginScreen(),
    ),
    GoRoute(
      path: '/otp-verify',
      builder: (context, state) => OtpVerifyScreen(phone: state.extra as String),
    ),
    GoRoute(
      path: '/onboarding',
      builder: (context, state) => const OnboardingScreen(),
    ),
    GoRoute(
      path: '/product/:uuid',
      builder: (context, state) =>
          ProductDetailScreen(uuid: state.pathParameters['uuid']!),
    ),
    GoRoute(
      path: '/checkout',
      builder: (context, state) => const CheckoutScreen(),
    ),
    GoRoute(
      path: '/order/:uuid',
      builder: (context, state) =>
          OrderDetailScreen(uuid: state.pathParameters['uuid']!),
    ),
    GoRoute(
      path: '/orders',
      builder: (context, state) => const _OrdersPage(),
    ),
    GoRoute(
      path: '/addresses',
      builder: (context, state) => const AddressesScreen(),
    ),
    GoRoute(
      path: '/addresses/select',
      builder: (context, state) => const AddressesScreen(selectMode: true),
    ),
    GoRoute(
      path: '/address/add',
      builder: (context, state) => const AddAddressScreen(),
    ),
    GoRoute(
      path: '/notifications',
      builder: (context, state) => const NotificationsScreen(),
    ),

    // Driver routes
    GoRoute(
      path: '/driver/login',
      builder: (context, state) => const DriverLoginScreen(),
    ),
    GoRoute(
      path: '/driver/home',
      builder: (context, state) => const DriverHomeScreen(),
    ),
    GoRoute(
      path: '/driver/delivery/:id',
      builder: (context, state) =>
          DeliveryDetailScreen(assignmentId: int.parse(state.pathParameters['id']!)),
    ),
  ],
);

class _OrdersPage extends StatelessWidget {
  const _OrdersPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: const OrdersScreen(),
    );
  }
}
