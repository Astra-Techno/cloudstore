import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/api_client.dart';
import '../../models/product.dart';
import '../../app/providers/cart_provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../widgets/price_text.dart';
import 'package:go_router/go_router.dart';
import '../../config/app_config.dart';

class ProductDetailScreen extends StatefulWidget {
  final String uuid;
  const ProductDetailScreen({super.key, required this.uuid});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  Product? _product;
  bool _loading = true;
  int _quantity = 1;
  ProductVariant? _selectedVariant;
  final Set<int> _selectedAddonIds = {};
  bool _adding = false;

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  Future<void> _loadProduct() async {
    try {
      final response = await ApiClient().get('/products/${widget.uuid}');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        setState(() {
          _product = Product.fromJson(data['data']);
          if (_product!.variants.isNotEmpty) {
            _selectedVariant = _product!.variants.first;
          }
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  int get _calculatedPrice {
    if (_product == null) return 0;
    int price;
    if (_product!.pricingMode == 'weight' && _selectedVariant != null) {
      // Weight product: variant price IS the full price for that weight
      price = _selectedVariant!.price;
    } else if (_selectedVariant != null) {
      // Fixed product with variant: variant price is the full price
      price = _selectedVariant!.price;
    } else {
      price = _product!.effectivePrice;
    }
    for (final group in _product!.addonGroups) {
      for (final item in group.items) {
        if (_selectedAddonIds.contains(item.id)) {
          price += item.price;
        }
      }
    }
    return price * _quantity;
  }

  Future<void> _addToCart() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      context.push('/login');
      return;
    }

    for (final group in _product!.addonGroups) {
      final selected = group.items.where((item) => _selectedAddonIds.contains(item.id)).length;
      if (selected < group.minSelections || selected > group.maxSelections) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Choose ${group.minSelections}-${group.maxSelections} option(s) for ${group.name}')));
        return;
      }
    }

    setState(() => _adding = true);

    final cart = context.read<CartProvider>();
    final success = await cart.addItem(
      productUuid: _product!.uuid,
      variantUuid: _selectedVariant?.uuid,
      quantity: _quantity,
      addonIds: _selectedAddonIds.toList(),
    );

    setState(() => _adding = false);

    if (mounted) {
      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Added to cart'), duration: Duration(seconds: 1)),
        );
        Navigator.of(context).pop();
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(cart.error ?? 'Failed to add')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_product?.name ?? 'Product')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _product == null
              ? const Center(child: Text('Product not found'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(22),
                        child: SizedBox(
                          width: double.infinity,
                          height: 240,
                          child: _product!.images.isEmpty
                              ? Container(
                                  decoration: const BoxDecoration(
                                    gradient: LinearGradient(
                                      colors: [Color(0xFFFFC47D), Color(0xFFFF7A59)],
                                      begin: Alignment.topLeft,
                                      end: Alignment.bottomRight,
                                    ),
                                  ),
                                  child: const Icon(Icons.restaurant_rounded, size: 64, color: Colors.white),
                                )
                              : Image.network(AppConfig.assetUrl(_product!.images.first.url), fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(
                                    decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFFFFC47D), Color(0xFFFF7A59)])),
                                    child: const Icon(Icons.restaurant_rounded, size: 64, color: Colors.white),
                                  )),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Name & price
                      Text(
                        _product!.name,
                        style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      const SizedBox(height: 8),
                      PriceText(
                        paise: _product!.effectivePrice,
                        showStrike: _product!.salePrice != null,
                        strikePrice: _product!.salePrice != null ? _product!.basePrice : null,
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                      ),
                      if (_product!.pricingMode == 'weight') ...[
                        const SizedBox(height: 4),
                        Text(
                          'per ${_product!.unit}',
                          style: TextStyle(color: Colors.grey[600]),
                        ),
                      ],

                      if (_product!.description != null) ...[
                        const SizedBox(height: 16),
                        Text(
                          _product!.description!,
                          style: TextStyle(color: Colors.grey[700], fontSize: 15),
                        ),
                      ],

                      // Variants / Weight selection
                      if (_product!.variants.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        Text(
                          _product!.pricingMode == 'weight' ? 'Choose Weight' : 'Choose Variant',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 8,
                          children: _product!.variants.map((v) {
                            final isSelected = _selectedVariant?.id == v.id;
                            final label = _product!.pricingMode == 'weight'
                                ? '${v.name} — ${PriceText.format(v.price)}'
                                : '${v.name} (${PriceText.format(v.price)})';
                            return ChoiceChip(
                              label: Text(label),
                              selected: isSelected,
                              onSelected: (_) => setState(() => _selectedVariant = v),
                            );
                          }).toList(),
                        ),
                      ],

                      // Addon groups
                      for (final group in _product!.addonGroups) ...[
                        const SizedBox(height: 24),
                        Text(
                          '${group.name} (select ${group.minSelections}-${group.maxSelections})',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        const SizedBox(height: 8),
                        ...group.items.map((item) {
                          final isSelected = _selectedAddonIds.contains(item.id);
                          return CheckboxListTile(
                            value: isSelected,
                            onChanged: (val) {
                              setState(() {
                                if (val == true) {
                                  _selectedAddonIds.add(item.id);
                                } else {
                                  _selectedAddonIds.remove(item.id);
                                }
                              });
                            },
                            title: Text(item.name),
                            subtitle: item.price > 0 ? Text('+${PriceText.format(item.price)}') : null,
                            contentPadding: EdgeInsets.zero,
                            controlAffinity: ListTileControlAffinity.leading,
                          );
                        }),
                      ],

                      // Quantity
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          Text('Quantity', style: Theme.of(context).textTheme.titleMedium),
                          const Spacer(),
                          IconButton.outlined(
                            onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null,
                            icon: const Icon(Icons.remove),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            child: Text(
                              '$_quantity',
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                            ),
                          ),
                          IconButton.outlined(
                            onPressed: () => setState(() => _quantity++),
                            icon: const Icon(Icons.add),
                          ),
                        ],
                      ),

                      const SizedBox(height: 100),
                    ],
                  ),
                ),
      bottomNavigationBar: _product != null
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton(
                  onPressed: _adding ? null : _addToCart,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: _adding
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : Text(
                          'Add to Cart - ${PriceText.format(_calculatedPrice)}',
                          style: const TextStyle(fontSize: 16),
                        ),
                ),
              ),
            )
          : null,
    );
  }
}
