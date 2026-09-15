import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/api_client.dart';
import '../../models/product.dart';
import '../../app/providers/cart_provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../app/providers/favourites_provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../widgets/price_text.dart';
import 'package:go_router/go_router.dart';
import '../../config/app_config.dart';
import '../../config/app_theme.dart';

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

  // Reviews
  List<Map<String, dynamic>> _reviews = [];
  double _avgRating = 0;
  int _reviewCount = 0;
  bool _reviewsLoading = false;

  @override
  void initState() {
    super.initState();
    _loadProduct();
    _loadReviews();
  }

  Future<void> _loadProduct() async {
    try {
      final response = await ApiClient().get('/products/${widget.uuid}');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        if (!mounted) return;
        setState(() {
          _product = Product.fromJson(data['data']);
          final availableVariants = _product!.variants
              .where((variant) => variant.status == 'active');
          if (availableVariants.isNotEmpty) {
            _selectedVariant = availableVariants.first;
          }
        });
      }
    } catch (_) {
      // The screen renders its standard not-found state if loading fails.
    }
    if (mounted) setState(() => _loading = false);
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
    final store = context.read<BootstrapProvider>();
    if (!store.isAcceptingOrders || _product == null || !_product!.isAvailable) {
      return;
    }
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      context.push('/login');
      return;
    }

    for (final group in _product!.addonGroups) {
      final selected = group.items
          .where((item) => _selectedAddonIds.contains(item.id))
          .length;
      if (selected < group.minSelections || selected > group.maxSelections) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text(
                'Choose ${group.minSelections}-${group.maxSelections} option(s) for ${group.name}')));
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

    if (!mounted) return;
    setState(() => _adding = false);

    if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Added to cart'), duration: Duration(seconds: 1)),
        );
        Navigator.of(context).pop();
    } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(cart.error ?? 'Failed to add')),
        );
    }
  }

  Future<void> _loadReviews() async {
    setState(() => _reviewsLoading = true);
    try {
      final ratingRes = await ApiClient().get('/products/${widget.uuid}/rating');
      final ratingData = ratingRes.data;
      if (ratingData['success'] == true && ratingData['data'] != null) {
        if (!mounted) return;
        setState(() {
          _avgRating = (ratingData['data']['average_rating'] as num?)?.toDouble() ?? 0;
          _reviewCount = (ratingData['data']['review_count'] as num?)?.toInt() ?? 0;
        });
      }

      final reviewsRes = await ApiClient().get('/products/${widget.uuid}/reviews');
      final reviewsData = reviewsRes.data;
      if (reviewsData['success'] == true && reviewsData['data'] is List) {
        if (!mounted) return;
        setState(() {
          _reviews = (reviewsData['data'] as List)
              .map((r) => Map<String, dynamic>.from(r as Map))
              .toList();
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _reviewsLoading = false);
  }

  Future<void> _submitReview() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      context.push('/login');
      return;
    }
    int selectedRating = 5;
    final commentController = TextEditingController();
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (sheetCtx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(20, 8, 20, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Write a Review', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800)),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (i) => IconButton(
                  onPressed: () => setSheetState(() => selectedRating = i + 1),
                  icon: Icon(
                    i < selectedRating ? Icons.star_rounded : Icons.star_outline_rounded,
                    color: Colors.amber,
                    size: 36,
                  ),
                )),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: commentController,
                maxLines: 3,
                decoration: const InputDecoration(
                  hintText: 'Share your experience (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () async {
                    try {
                      final res = await ApiClient().post('/customer/reviews', data: {
                        'product_uuid': widget.uuid,
                        'rating': selectedRating,
                        'comment': commentController.text.trim().isEmpty ? null : commentController.text.trim(),
                      });
                      if (res.data['success'] == true && ctx.mounted) {
                        Navigator.pop(ctx, true);
                      } else if (ctx.mounted) {
                        ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(
                          content: Text(res.data['error']?['message'] ?? 'Failed to submit review'),
                        ));
                      }
                    } catch (_) {
                      if (ctx.mounted) {
                        ScaffoldMessenger.of(ctx).showSnackBar(
                          const SnackBar(content: Text('Failed to submit review')),
                        );
                      }
                    }
                  },
                  child: const Text('Submit Review'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
    commentController.dispose();
    if (submitted == true) {
      _loadReviews();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Review submitted!')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final store = context.watch<BootstrapProvider>();
    final canOrder = _product != null &&
        _product!.isAvailable &&
        store.isAcceptingOrders;
    return Scaffold(
      appBar: AppBar(
        title: Text(_product?.name ?? 'Product'),
        actions: [
          if (_product != null)
            Consumer<FavouritesProvider>(
              builder: (context, favs, _) {
                final isFav = favs.isFavourite(_product!.uuid);
                return IconButton(
                  onPressed: () {
                    final auth = context.read<AuthProvider>();
                    if (!auth.isAuthenticated) {
                      context.push('/login');
                      return;
                    }
                    favs.toggleFavourite(_product!.uuid);
                  },
                  icon: Icon(
                    isFav ? Icons.favorite : Icons.favorite_border,
                    color: isFav
                        ? Colors.red
                        : Theme.of(context).colorScheme.onSurface,
                  ),
                );
              },
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _product == null
              ? const Center(child: Text('Product not found'))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (!canOrder)
                        Container(
                          width: double.infinity,
                          margin: const EdgeInsets.only(bottom: 16),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.grey.shade200,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(children: [
                            Icon(Icons.info_outline, color: Colors.grey.shade700),
                            const SizedBox(width: 8),
                            Expanded(child: Text(
                              _product!.isAvailable
                                  ? store.orderingMessage
                                  : 'This item is currently unavailable',
                              style: TextStyle(color: Colors.grey.shade800),
                            )),
                          ]),
                        ),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(22),
                        child: SizedBox(
                          width: double.infinity,
                          height: 240,
                          child: _product!.images.isEmpty
                              ? Container(
                                  decoration: BoxDecoration(
                                    gradient: AppTheme.primaryGradient(
                                        Theme.of(context).colorScheme.primary),
                                  ),
                                  child: const Icon(Icons.restaurant_rounded,
                                      size: 64, color: Colors.white),
                                )
                              : Image.network(
                                  AppConfig.assetUrl(
                                      _product!.images.first.url),
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) => Container(
                                        decoration: BoxDecoration(
                                            gradient: AppTheme.primaryGradient(
                                                Theme.of(context)
                                                    .colorScheme
                                                    .primary)),
                                        child: const Icon(
                                            Icons.restaurant_rounded,
                                            size: 64,
                                            color: Colors.white),
                                      )),
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Name & price
                      Text(
                        _product!.name,
                        style:
                            Theme.of(context).textTheme.headlineSmall?.copyWith(
                                  fontWeight: FontWeight.bold,
                                ),
                      ),
                      const SizedBox(height: 8),
                      PriceText(
                        paise: _product!.effectivePrice,
                        showStrike: _product!.salePrice != null,
                        strikePrice: _product!.salePrice != null
                            ? _product!.basePrice
                            : null,
                        style: const TextStyle(
                            fontSize: 22, fontWeight: FontWeight.bold),
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
                          style:
                              TextStyle(color: Colors.grey[700], fontSize: 15),
                        ),
                      ],

                      // Variants / Weight selection
                      if (_product!.variants.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        Text(
                          _product!.pricingMode == 'weight'
                              ? 'Choose Weight'
                              : 'Choose Variant',
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
                              onSelected: canOrder && v.status == 'active'
                                  ? (_) => setState(() => _selectedVariant = v)
                                  : null,
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
                          final isSelected =
                              _selectedAddonIds.contains(item.id);
                          return CheckboxListTile(
                            value: isSelected,
                            onChanged: canOrder ? (val) {
                              setState(() {
                                if (val == true) {
                                  _selectedAddonIds.add(item.id);
                                } else {
                                  _selectedAddonIds.remove(item.id);
                                }
                              });
                            } : null,
                            title: Text(item.name),
                            subtitle: item.price > 0
                                ? Text('+${PriceText.format(item.price)}')
                                : null,
                            contentPadding: EdgeInsets.zero,
                            controlAffinity: ListTileControlAffinity.leading,
                          );
                        }),
                      ],

                      // Ratings & Reviews
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          Text('Ratings & Reviews', style: Theme.of(context).textTheme.titleMedium),
                          const Spacer(),
                          TextButton.icon(
                            onPressed: _submitReview,
                            icon: const Icon(Icons.rate_review_outlined, size: 18),
                            label: const Text('Write Review'),
                          ),
                        ],
                      ),
                      if (_reviewCount > 0) ...[
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            ...List.generate(5, (i) => Icon(
                              i < _avgRating.round() ? Icons.star_rounded : Icons.star_outline_rounded,
                              color: Colors.amber,
                              size: 20,
                            )),
                            const SizedBox(width: 8),
                            Text(
                              '${_avgRating.toStringAsFixed(1)} ($_reviewCount ${_reviewCount == 1 ? 'review' : 'reviews'})',
                              style: TextStyle(color: Colors.grey[600], fontSize: 13),
                            ),
                          ],
                        ),
                      ],
                      if (_reviews.isNotEmpty) ...[
                        const SizedBox(height: 12),
                        ...(_reviews.take(3).map((r) => Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Card(
                            child: Padding(
                              padding: const EdgeInsets.all(12),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(children: [
                                    ...List.generate(5, (i) => Icon(
                                      i < ((r['rating'] as num?)?.toInt() ?? 0) ? Icons.star_rounded : Icons.star_outline_rounded,
                                      color: Colors.amber,
                                      size: 16,
                                    )),
                                    const Spacer(),
                                    Text(
                                      r['customer_name']?.toString() ?? 'Customer',
                                      style: TextStyle(color: Colors.grey[600], fontSize: 12),
                                    ),
                                  ]),
                                  if (r['comment'] != null && r['comment'].toString().isNotEmpty) ...[
                                    const SizedBox(height: 6),
                                    Text(r['comment'].toString(), style: const TextStyle(fontSize: 13)),
                                  ],
                                ],
                              ),
                            ),
                          ),
                        ))),
                      ] else if (!_reviewsLoading) ...[
                        const SizedBox(height: 8),
                        Text('No reviews yet. Be the first!', style: TextStyle(color: Colors.grey[500], fontSize: 13)),
                      ],

                      // Quantity
                      const SizedBox(height: 24),
                      Row(
                        children: [
                          Text('Quantity',
                              style: Theme.of(context).textTheme.titleMedium),
                          const Spacer(),
                          IconButton.outlined(
                            onPressed: canOrder && _quantity > 1
                                ? () => setState(() => _quantity--)
                                : null,
                            icon: const Icon(Icons.remove),
                          ),
                          Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            child: Text(
                              '$_quantity',
                              style: const TextStyle(
                                  fontSize: 20, fontWeight: FontWeight.bold),
                            ),
                          ),
                          IconButton.outlined(
                            onPressed: canOrder
                                ? () => setState(() => _quantity++)
                                : null,
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
                  onPressed: _adding || !canOrder ? null : _addToCart,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: _adding
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : Text(
                          canOrder
                              ? 'Add to Cart - ${PriceText.format(_calculatedPrice)}'
                              : _product!.isAvailable
                                  ? 'Store currently closed'
                                  : 'Currently unavailable',
                          style: const TextStyle(fontSize: 16),
                        ),
                ),
              ),
            )
          : null,
    );
  }
}
