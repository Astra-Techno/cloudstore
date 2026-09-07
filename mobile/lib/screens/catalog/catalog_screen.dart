import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../../services/api_client.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../widgets/price_text.dart';
import '../../widgets/loading_overlay.dart';

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
    return Column(
      children: [
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
              : _products.isEmpty
                  ? const EmptyState(
                      icon: Icons.fastfood_outlined,
                      title: 'No products in this category',
                    )
                  : RefreshIndicator(
                      onRefresh: () => _selectCategory(_selectedCategoryUuid!),
                      child: ListView.builder(
                        padding: const EdgeInsets.all(12),
                        itemCount: _products.length,
                        itemBuilder: (context, index) {
                          final product = _products[index];
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
          padding: const EdgeInsets.all(16),
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
                  Icons.fastfood,
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
