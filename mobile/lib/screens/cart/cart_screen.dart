import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/cart_provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../widgets/price_text.dart';
import '../../widgets/state_widgets.dart';
import '../../widgets/motion_widgets.dart';
import '../../config/app_theme.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  @override
  void initState() {
    super.initState();
    context.read<CartProvider>().loadCart();
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final auth = context.watch<AuthProvider>();
    final store = context.watch<BootstrapProvider>();
    final hasUnavailableItems = cart.items.any((item) => !item.isAvailable);

    if (!auth.isAuthenticated) {
      return EmptyStateWidget(
        icon: Icons.shopping_cart_outlined,
        title: 'Login to view your cart',
        subtitle: 'Sign in to add items and place an order.',
        actionLabel: 'Login',
        onAction: () => context.push('/login'),
      );
    }

    if (cart.isLoading && cart.items.isEmpty) {
      return const LoadingStateWidget(message: 'Loading your cart...');
    }

    if (cart.error != null && cart.items.isEmpty) {
      return ErrorStateWidget(
        message: cart.error!,
        onRetry: () => cart.loadCart(),
      );
    }

    if (cart.isEmpty) {
      return EmptyStateWidget(
        icon: Icons.shopping_cart_outlined,
        title: 'Your cart is empty',
        subtitle: 'Browse our menu and add items',
        actionLabel: 'Browse Menu',
        onAction: () => context.go('/home'),
      );
    }

    return Column(
      children: [
        if (cart.error != null && cart.items.isNotEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: Colors.red.shade50,
            child: Text(cart.error!,
                style: TextStyle(color: Colors.red.shade800, fontSize: 13)),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: cart.loadCart,
            child: ListView.builder(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
              itemCount: cart.items.length + 1,
              itemBuilder: (context, index) {
                if (index == 0) {
                  final primary = Theme.of(context).colorScheme.primary;
                  return StaggeredEntrance(
                    index: 0,
                    child: Container(
                      margin: const EdgeInsets.fromLTRB(0, 4, 0, 18),
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        gradient: AppTheme.primaryGradient(primary),
                        borderRadius: BorderRadius.circular(24),
                      ),
                      child: Row(children: [
                        const CircleAvatar(
                            backgroundColor: Colors.white24,
                            child: Icon(Icons.shopping_bag_rounded,
                                color: Colors.white)),
                        const SizedBox(width: 12),
                        Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Your order',
                                  style: TextStyle(
                                      color: Colors.white,
                                      fontSize: 21,
                                      fontWeight: FontWeight.w900)),
                              const SizedBox(height: 3),
                              AnimatedSwitcher(
                                duration: const Duration(milliseconds: 180),
                                child: Text(
                                    '${cart.itemCount} ${cart.itemCount == 1 ? 'item' : 'items'} ready for checkout',
                                    key: ValueKey(cart.itemCount),
                                    style: const TextStyle(
                                        color: Colors.white70, fontSize: 13)),
                              ),
                            ]),
                      ]),
                    ),
                  );
                }
                final item = cart.items[index - 1];
                final changingQuantity = cart.isUpdatingItem(item.id);
                return StaggeredEntrance(
                  index: index,
                  child: Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    color: !item.isAvailable
                        ? Colors.grey.shade200
                        : index.isOdd
                            ? Colors.white
                            : AppTheme.primaryLight(
                                Theme.of(context).colorScheme.primary),
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.productName,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 15),
                                ),
                                if (item.variantName != null)
                                  Text(
                                    item.variantName!,
                                    style: TextStyle(
                                        color: Colors.grey[600], fontSize: 13),
                                  ),
                                if (item.addonNames.isNotEmpty)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 2),
                                    child: Text(
                                      item.addonNames.join(', '),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                          color: Colors.grey[600],
                                          fontSize: 12),
                                    ),
                                  ),
                                const SizedBox(height: 4),
                                PriceText(paise: item.lineTotal),
                                if (!item.isAvailable)
                                  Padding(
                                    padding: const EdgeInsets.only(top: 4),
                                    child: Text('Currently unavailable',
                                        style: TextStyle(
                                            color: Colors.grey.shade700,
                                            fontSize: 12,
                                            fontWeight: FontWeight.w700)),
                                  ),
                              ],
                            ),
                          ),
                          Row(
                            children: [
                              IconButton(
                                icon: Icon(Icons.remove_circle_outline,
                                    color:
                                        Theme.of(context).colorScheme.primary),
                                iconSize: 28,
                                onPressed: !item.isAvailable || changingQuantity
                                    ? null
                                    : () {
                                        if (item.quantity > 1) {
                                          cart.updateItem(
                                              item.id, item.quantity - 1);
                                        } else {
                                          cart.removeItem(item.id);
                                        }
                                      },
                              ),
                              AnimatedSwitcher(
                                duration: const Duration(milliseconds: 160),
                                transitionBuilder: (child, animation) =>
                                    ScaleTransition(
                                  scale: animation,
                                  child: FadeTransition(
                                      opacity: animation, child: child),
                                ),
                                child: changingQuantity
                                    ? const SizedBox(
                                        key: ValueKey('updating'),
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                            strokeWidth: 2),
                                      )
                                    : Text(
                                        '${item.quantity}',
                                        key: ValueKey(item.quantity),
                                        style: const TextStyle(
                                            fontSize: 18,
                                            fontWeight: FontWeight.bold),
                                      ),
                              ),
                              IconButton(
                                icon: Icon(Icons.add_circle,
                                    color:
                                        Theme.of(context).colorScheme.primary),
                                iconSize: 28,
                                onPressed: item.isAvailable && !changingQuantity
                                    ? () => cart.updateItem(
                                        item.id, item.quantity + 1)
                                    : null,
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ),

        // Bottom bar
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.surface,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withAlpha(15),
                blurRadius: 8,
                offset: const Offset(0, -2),
              ),
            ],
          ),
          child: SafeArea(
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Subtotal (${cart.itemCount} items)',
                      style: const TextStyle(fontSize: 16),
                    ),
                    Text(
                      PriceText.format(cart.subtotal),
                      style: const TextStyle(
                          fontSize: 20, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Delivery fee, taxes, and discounts are confirmed at checkout.',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  ),
                ),
                const SizedBox(height: 12),
                if (!store.isAcceptingOrders || hasUnavailableItems) ...[
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade200,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(children: [
                      Icon(Icons.schedule_rounded, color: Colors.grey.shade700),
                      const SizedBox(width: 8),
                      Expanded(
                          child: Text(
                              hasUnavailableItems
                                  ? 'Remove unavailable items before checkout.'
                                  : store.orderingMessage,
                              style: TextStyle(color: Colors.grey.shade800))),
                    ]),
                  ),
                ],
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: store.isAcceptingOrders && !hasUnavailableItems
                        ? () => context.push('/checkout')
                        : null,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                    child: Text(
                        store.isAcceptingOrders && !hasUnavailableItems
                            ? 'Proceed to Checkout'
                            : 'Ordering unavailable',
                        style: const TextStyle(fontSize: 16)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
