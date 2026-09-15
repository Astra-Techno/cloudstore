import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../app/providers/cart_provider.dart';
import '../app/providers/notification_provider.dart';
import '../app/providers/bootstrap_provider.dart';
import '../app/providers/location_provider.dart';
import '../config/app_config.dart';
import 'catalog/catalog_screen.dart';
import 'orders/orders_screen.dart';
import 'cart/cart_screen.dart';
import 'profile/profile_screen.dart';
import 'location/location_setup_screen.dart';

class HomeScreen extends StatefulWidget {
  final int initialIndex;

  const HomeScreen({super.key, this.initialIndex = 0});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  late int _currentIndex;

  final _screens = const [
    CatalogScreen(),
    OrdersScreen(),
    CartScreen(),
    ProfileScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex.clamp(0, _screens.length - 1);
  }

  @override
  Widget build(BuildContext context) {
    // Also guard direct navigation to /home, so the menu can never flash
    // before a serviceable GPS/map location is confirmed.
    if (!context.watch<LocationProvider>().hasConfirmedLocation) {
      return const LocationSetupScreen();
    }
    final cart = context.watch<CartProvider>();
    final notifications = context.watch<NotificationProvider>();
    final tenant = context.watch<BootstrapProvider>();

    return Scaffold(
      // The menu has its own compact, SafeArea-aware header. Keeping an empty
      // app bar above it wastes a full toolbar height on small phones.
      appBar: _currentIndex == 0
          ? null
          : AppBar(
              title: Row(
                children: [
                  if (tenant.logoUrl != null && tenant.logoUrl!.isNotEmpty) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(6),
                      child: Image.network(
                        AppConfig.assetUrl(tenant.logoUrl!),
                        width: 32,
                        height: 32,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                      ),
                    ),
                    const SizedBox(width: 10),
                  ],
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(_titles[_currentIndex]),
                      if (tenant.tenantName != null &&
                          tenant.businessType != null)
                        Text(tenant.businessType!,
                            style: const TextStyle(
                                fontSize: 11, fontWeight: FontWeight.w500)),
                    ],
                  ),
                ],
              ),
              actions: [
                if (AppConfig.appMode == 'marketplace')
                  IconButton(
                    tooltip: 'Change store',
                    icon: const Icon(Icons.storefront_outlined),
                    onPressed: () => context.go('/marketplace'),
                  ),
                if (notifications.unreadCount > 0)
                  Badge(
                    label: Text('${notifications.unreadCount}'),
                    child: IconButton(
                      icon: const Icon(Icons.notifications_outlined),
                      onPressed: () =>
                          GoRouter.of(context).push('/notifications'),
                    ),
                  )
                else
                  IconButton(
                    icon: const Icon(Icons.notifications_outlined),
                    onPressed: () =>
                        GoRouter.of(context).push('/notifications'),
                  ),
              ],
            ),
      body: _screens[_currentIndex],
      bottomNavigationBar: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (cart.itemCount > 0 && _currentIndex != 2)
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: FilledButton.icon(
                  onPressed: () => setState(() => _currentIndex = 2),
                  icon: const Icon(Icons.shopping_bag_outlined),
                  label: Text(
                      'View cart · ${cart.itemCount} ${cart.itemCount == 1 ? 'item' : 'items'}'),
                ),
              ),
            ),
          NavigationBar(
            selectedIndex: _currentIndex,
            onDestinationSelected: (index) =>
                setState(() => _currentIndex = index),
            destinations: [
              const NavigationDestination(
                icon: Icon(Icons.restaurant_menu_outlined),
                selectedIcon: Icon(Icons.restaurant_menu),
                label: 'Menu',
              ),
              const NavigationDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long),
                label: 'Orders',
              ),
              NavigationDestination(
                icon: Badge(
                  isLabelVisible: cart.itemCount > 0,
                  label: Text('${cart.itemCount}'),
                  child: const Icon(Icons.shopping_cart_outlined),
                ),
                selectedIcon: Badge(
                  isLabelVisible: cart.itemCount > 0,
                  label: Text('${cart.itemCount}'),
                  child: const Icon(Icons.shopping_cart),
                ),
                label: 'Cart',
              ),
              const NavigationDestination(
                icon: Icon(Icons.person_outline),
                selectedIcon: Icon(Icons.person),
                label: 'Profile',
              ),
            ],
          ),
        ],
      ),
    );
  }

  static const _titles = ['Menu', 'My Orders', 'Cart', 'Profile'];
}
