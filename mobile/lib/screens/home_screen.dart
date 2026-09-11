import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../app/providers/cart_provider.dart';
import '../app/providers/notification_provider.dart';
import '../app/providers/bootstrap_provider.dart';
import 'catalog/catalog_screen.dart';
import 'orders/orders_screen.dart';
import 'cart/cart_screen.dart';
import 'profile/profile_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;

  final _screens = const [
    CatalogScreen(),
    OrdersScreen(),
    CartScreen(),
    ProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final notifications = context.watch<NotificationProvider>();
    final tenant = context.watch<BootstrapProvider>();

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            if (_currentIndex == 0 && tenant.logoUrl != null && tenant.logoUrl!.isNotEmpty) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Image.network(
                  tenant.logoUrl!,
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
                Text(_currentIndex == 0 ? (tenant.tenantName ?? _titles[_currentIndex]) : _titles[_currentIndex]),
                if (_currentIndex == 0 && tenant.tenantName != null && tenant.businessType != null)
                  Text(tenant.businessType!, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w500)),
              ],
            ),
          ],
        ),
        actions: [
          if (notifications.unreadCount > 0)
            Badge(
              label: Text('${notifications.unreadCount}'),
              child: IconButton(
                icon: const Icon(Icons.notifications_outlined),
                onPressed: () => GoRouter.of(context).push('/notifications'),
              ),
            )
          else
            IconButton(
              icon: const Icon(Icons.notifications_outlined),
              onPressed: () => GoRouter.of(context).push('/notifications'),
            ),
        ],
      ),
      body: _screens[_currentIndex],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: (index) => setState(() => _currentIndex = index),
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
    );
  }

  static const _titles = ['Menu', 'My Orders', 'Cart', 'Profile'];
}
