import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../app/providers/auth_provider.dart';
import '../../app/providers/favourites_provider.dart';
import '../../config/app_theme.dart';
import '../../models/product.dart';
import '../../services/api_client.dart';
import '../../widgets/price_text.dart';
import '../../widgets/state_widgets.dart';

/// A dedicated saved-items surface makes favourites useful beyond the heart
/// icon in the catalogue. Products are always fetched from the server so a
/// removed or deactivated product cannot be presented as purchasable.
class FavouritesScreen extends StatefulWidget {
  const FavouritesScreen({super.key});

  @override
  State<FavouritesScreen> createState() => _FavouritesScreenState();
}

class _FavouritesScreenState extends State<FavouritesScreen> {
  List<Product> _products = const [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await ApiClient().get('/customer/favourites');
      final body = response.data;
      if (body is Map && body['success'] == true && body['data'] is List) {
        final products = (body['data'] as List)
            .whereType<Map>()
            .map((raw) => Product.fromJson(Map<String, dynamic>.from(raw)))
            .toList();
        if (mounted) {
          setState(() => _products = products);
        }
      } else if (mounted) {
        setState(() => _error = 'Unable to load saved items.');
      }
    } catch (_) {
      if (mounted) {
        setState(() =>
            _error = 'Unable to load saved items. Check your connection.');
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _remove(Product product) async {
    await context.read<FavouritesProvider>().toggleFavourite(product.uuid);
    if (mounted) {
      setState(() => _products =
          _products.where((item) => item.uuid != product.uuid).toList());
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${product.name} removed from saved items')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!context.watch<AuthProvider>().isAuthenticated) {
      return Scaffold(
        appBar: AppBar(title: const Text('Saved items')),
        body: EmptyStateWidget(
          icon: Icons.favorite_border_rounded,
          title: 'Save your favourites',
          subtitle: 'Log in to keep dishes you love in one place.',
          actionLabel: 'Log in',
          onAction: () => context.push('/login'),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Saved items')),
      body: _loading
          ? const LoadingStateWidget(message: 'Loading saved items...')
          : _error != null
              ? ErrorStateWidget(message: _error!, onRetry: _load)
              : _products.isEmpty
                  ? EmptyStateWidget(
                      icon: Icons.favorite_border_rounded,
                      title: 'No saved items yet',
                      subtitle:
                          'Tap the heart on a dish to find it quickly later.',
                      actionLabel: 'Explore menu',
                      onAction: () => context.go('/home'),
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                        itemCount: _products.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final product = _products[index];
                          final primary = Theme.of(context).colorScheme.primary;
                          return Card(
                            child: ListTile(
                              onTap: () =>
                                  context.push('/product/${product.uuid}'),
                              contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 8),
                              leading: Container(
                                width: 48,
                                height: 48,
                                decoration: BoxDecoration(
                                  color: AppTheme.primaryLight(primary),
                                  borderRadius: BorderRadius.circular(14),
                                ),
                                child: Icon(Icons.restaurant_rounded,
                                    color: primary),
                              ),
                              title: Text(product.name,
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w800)),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (product.categoryName?.isNotEmpty == true)
                                    Text(product.categoryName!),
                                  const SizedBox(height: 3),
                                  PriceText(paise: product.effectivePrice),
                                ],
                              ),
                              trailing: IconButton(
                                tooltip: 'Remove from saved items',
                                icon: Icon(Icons.favorite_rounded,
                                    color: primary),
                                onPressed: () => _remove(product),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}
