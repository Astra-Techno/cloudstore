import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_client.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../widgets/price_text.dart';
import '../../widgets/loading_overlay.dart';
import '../../app/providers/bootstrap_provider.dart';
import 'package:provider/provider.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});

  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  List<Category> _categories = [];
  List<Product> _products = [];
  String? _selectedCategoryUuid;
  bool _loading = true;
  String _query = '';

  List<Product> get _visibleProducts {
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return _products;
    return _products.where((product) {
      return product.name.toLowerCase().contains(query) ||
          (product.description?.toLowerCase().contains(query) ?? false);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _loadCategories();
  }

  Future<void> _loadCategories() async {
    try {
      final response = await ApiClient().get('/categories');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final list = data['data'] as List<dynamic>;
        setState(() {
          _categories = list.map((c) => Category.fromJson(c)).toList();
          _loading = false;
        });
        if (_categories.isNotEmpty) {
          _selectCategory(_categories.first.uuid);
        } else {
          setState(() => _loading = false);
        }
      }
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  Future<void> _selectCategory(String uuid) async {
    setState(() {
      _selectedCategoryUuid = uuid;
      _loading = true;
    });

    try {
      final response = await ApiClient().get('/categories/$uuid/products');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final list = data['data'] as List<dynamic>;
        setState(() {
          _products = list.map((p) => Product.fromJson(p)).toList();
        });
      }
    } catch (_) {}

    setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    final tenant = context.watch<BootstrapProvider>();
    return Column(
      children: [
        Container(
          width: double.infinity,
          margin: const EdgeInsets.fromLTRB(12, 12, 12, 4),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Row(
            children: [
              Icon(Icons.storefront_rounded, color: Theme.of(context).colorScheme.primary),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Order directly from ${tenant.tenantName ?? 'your local store'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 3),
                const Text('Fresh products, pickup or delivery.', style: TextStyle(fontSize: 12)),
              ])),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
          child: TextField(
            onChanged: (value) => setState(() => _query = value),
            textInputAction: TextInputAction.search,
            decoration: InputDecoration(
              hintText: 'Search this category',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: _query.isEmpty
                  ? null
                  : IconButton(
                      tooltip: 'Clear search',
                      onPressed: () => setState(() => _query = ''),
                      icon: const Icon(Icons.clear),
                    ),
              filled: true,
              fillColor: Theme.of(context).colorScheme.surface,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide.none,
              ),
            ),
          ),
        ),
        // Category tabs
        if (_categories.isNotEmpty)
          SizedBox(
            height: 50,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              itemCount: _categories.length,
              itemBuilder: (context, index) {
                final cat = _categories[index];
                final isSelected = cat.uuid == _selectedCategoryUuid;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(cat.name),
                    selected: isSelected,
                    onSelected: (_) => _selectCategory(cat.uuid),
                  ),
                );
              },
            ),
          ),

        // Products grid
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _visibleProducts.isEmpty
                  ? EmptyState(
                      icon: Icons.fastfood_outlined,
                      title: _query.isEmpty
                          ? 'No products in this category'
                          : 'No matching products',
                      subtitle: _query.isEmpty ? null : 'Try a different search term.',
                    )
                  : RefreshIndicator(
                      onRefresh: () => _selectCategory(_selectedCategoryUuid!),
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _visibleProducts.length,
                        itemBuilder: (context, index) {
                          final product = _visibleProducts[index];
                          return _ProductCard(product: product);
                        },
                      ),
                    ),
        ),
      ],
    );
  }
}

class _ProductCard extends StatelessWidget {
  final Product product;
  const _ProductCard({required this.product});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => context.push('/product/${product.uuid}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.primaryContainer,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  product.pricingMode == 'weight' ? Icons.scale_outlined : Icons.fastfood,
                  color: Theme.of(context).colorScheme.primary,
                  size: 28,
                ),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.name,
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16),
                    ),
                    if (product.description != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        product.description!,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: Colors.grey[600], fontSize: 13),
                      ),
                    ],
                    const SizedBox(height: 8),
                    PriceText(
                      paise: product.effectivePrice,
                      showStrike: product.salePrice != null,
                      strikePrice: product.salePrice != null ? product.basePrice : null,
                    ),
                    if (product.stockMode != 'unlimited' && (product.stockQuantity ?? 0) <= 5) ...[
                      const SizedBox(height: 5),
                      Text('Only ${product.stockQuantity} left', style: TextStyle(color: Theme.of(context).colorScheme.error, fontSize: 11, fontWeight: FontWeight.w600)),
                    ],
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: Colors.grey[400]),
            ],
          ),
        ),
      ),
    );
  }
}
