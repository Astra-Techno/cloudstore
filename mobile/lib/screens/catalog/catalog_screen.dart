import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../models/category.dart';
import '../../models/product.dart';
import '../../services/api_client.dart';
import '../../widgets/price_text.dart';
import '../../config/app_config.dart';

class CatalogScreen extends StatefulWidget {
  const CatalogScreen({super.key});
  @override
  State<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends State<CatalogScreen> {
  final _search = TextEditingController();
  List<Category> _categories = [];
  List<Product> _products = [];
  String? _category;
  String _query = '';
  bool _loading = true;

  @override
  void initState() { super.initState(); _load(); }
  @override
  void dispose() { _search.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final response = await ApiClient().get('/catalog');
      final data = response.data;
      if (data['success'] == true && data['data'] is List) {
        final categories = <Category>[];
        final products = <Product>[];
        for (final raw in data['data'] as List) {
          final section = Map<String, dynamic>.from(raw as Map);
          if (section['category'] is Map) categories.add(Category.fromJson(Map<String, dynamic>.from(section['category'] as Map)));
          if (section['products'] is List) products.addAll((section['products'] as List).map((item) => Product.fromJson(Map<String, dynamic>.from(item as Map))));
        }
        if (mounted) setState(() { _categories = categories; _products = products; });
      }
    } catch (_) {
      // The retryable empty state is intentional for an offline or unavailable store.
    } finally { if (mounted) setState(() => _loading = false); }
  }

  List<Product> get _visible {
    final term = _query.trim().toLowerCase();
    final categoryName = _category == null ? null : _categories.where((item) => item.uuid == _category).map((item) => item.name).firstOrNull;
    return _products.where((product) =>
      (categoryName == null || product.categoryName == categoryName) &&
      (term.isEmpty || product.name.toLowerCase().contains(term) || (product.description?.toLowerCase().contains(term) ?? false))
    ).toList();
  }

  @override
  Widget build(BuildContext context) {
    final tenant = context.watch<BootstrapProvider>();
    final primary = Theme.of(context).colorScheme.primary;
    return RefreshIndicator(
      onRefresh: _load,
      child: CustomScrollView(physics: const AlwaysScrollableScrollPhysics(), slivers: [
        SliverToBoxAdapter(child: Padding(padding: const EdgeInsets.fromLTRB(18, 14, 18, 12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Order from ${tenant.tenantName ?? 'your favourite store'}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, letterSpacing: -.5)),
          const SizedBox(height: 5),
          Text(tenant.deliveryEnabled && tenant.pickupEnabled ? 'Self delivery and easy pickup' : tenant.pickupEnabled ? 'Easy store pickup available' : 'Freshly prepared for you', style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
          const SizedBox(height: 16),
          TextField(controller: _search, onChanged: (value) => setState(() => _query = value), decoration: InputDecoration(
            hintText: 'Search for dishes, items and more', prefixIcon: const Icon(Icons.search_rounded),
            suffixIcon: _query.isEmpty ? null : IconButton(icon: const Icon(Icons.close_rounded), onPressed: () { _search.clear(); setState(() => _query = ''); }),
            filled: true, fillColor: Colors.white, contentPadding: const EdgeInsets.symmetric(vertical: 15),
            border: OutlineInputBorder(borderSide: BorderSide(color: Colors.grey.shade200), borderRadius: BorderRadius.circular(16)),
            enabledBorder: OutlineInputBorder(borderSide: BorderSide(color: Colors.grey.shade200), borderRadius: BorderRadius.circular(16)),
          )),
        ]))),
        if (_categories.isNotEmpty) SliverToBoxAdapter(child: SizedBox(height: 104, child: ListView.separated(padding: const EdgeInsets.symmetric(horizontal: 18), scrollDirection: Axis.horizontal, itemCount: _categories.length + 1, separatorBuilder: (_, __) => const SizedBox(width: 12), itemBuilder: (context, index) {
          final all = index == 0; final category = all ? null : _categories[index - 1]; final selected = all ? _category == null : category!.uuid == _category;
          return InkWell(borderRadius: BorderRadius.circular(16), onTap: () => setState(() => _category = all ? null : category!.uuid), child: SizedBox(width: 72, child: Column(children: [
            AnimatedContainer(duration: const Duration(milliseconds: 180), width: 62, height: 62, decoration: BoxDecoration(color: selected ? primary : Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: selected ? primary : Colors.grey.shade200)), child: category?.imageUrl != null && category!.imageUrl!.isNotEmpty ? ClipRRect(borderRadius: BorderRadius.circular(17), child: Image.network(AppConfig.assetUrl(category.imageUrl!), fit: BoxFit.cover, errorBuilder: (_, __, ___) => Icon(Icons.restaurant_menu_rounded, color: selected ? Colors.white : primary))) : Icon(all ? Icons.grid_view_rounded : Icons.restaurant_rounded, color: selected ? Colors.white : primary)),
            const SizedBox(height: 6), Text(all ? 'All' : category!.name, maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center, style: TextStyle(fontSize: 11, fontWeight: selected ? FontWeight.w800 : FontWeight.w600)),
          ])));
        }))),
        SliverToBoxAdapter(child: Padding(padding: const EdgeInsets.fromLTRB(18, 14, 18, 12), child: Row(children: [Text(_query.isEmpty && _category == null ? 'Explore the menu' : '${_visible.length} items found', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)), const Spacer(), if (!_loading) Text('Freshly made', style: TextStyle(color: primary, fontSize: 12, fontWeight: FontWeight.w700))]))),
        if (_loading) const SliverFillRemaining(child: Center(child: CircularProgressIndicator()))
        else if (_visible.isEmpty) SliverFillRemaining(child: _EmptyCatalog(onRetry: _load))
        else SliverPadding(padding: const EdgeInsets.fromLTRB(18, 0, 18, 108), sliver: SliverList(delegate: SliverChildBuilderDelegate((context, index) => _ProductRow(product: _visible[index]), childCount: _visible.length))),
      ]),
    );
  }
}

class _ProductRow extends StatelessWidget {
  final Product product;
  const _ProductRow({required this.product});
  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    final image = product.images.where((item) => item.isPrimary).firstOrNull ?? (product.images.isNotEmpty ? product.images.first : null);
    return InkWell(onTap: () => context.push('/product/${product.uuid}'), borderRadius: BorderRadius.circular(18), child: Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Expanded(child: Padding(padding: const EdgeInsets.only(top: 4, right: 14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [Icon(product.pricingMode == 'weight' ? Icons.eco_outlined : Icons.circle_outlined, size: 15, color: product.pricingMode == 'weight' ? Colors.green : Colors.red), const SizedBox(width: 5), Text(product.categoryName ?? 'Popular', style: TextStyle(color: Colors.grey.shade600, fontSize: 11))]),
        const SizedBox(height: 5), Text(product.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        if (product.description?.isNotEmpty == true) Padding(padding: const EdgeInsets.only(top: 4), child: Text(product.description!, maxLines: 2, overflow: TextOverflow.ellipsis, style: TextStyle(color: Colors.grey.shade600, fontSize: 12))),
        const SizedBox(height: 8), PriceText(paise: product.effectivePrice, showStrike: product.salePrice != null, strikePrice: product.salePrice != null ? product.basePrice : null),
        if (product.stockMode == 'limited_stock' && (product.stockQuantity ?? 0) <= 5) Padding(padding: const EdgeInsets.only(top: 5), child: Text('Only ${product.stockQuantity} left', style: const TextStyle(color: Colors.red, fontSize: 11, fontWeight: FontWeight.w700))),
      ]))),
      SizedBox(width: 118, child: Column(children: [
        ClipRRect(borderRadius: BorderRadius.circular(16), child: SizedBox(width: 118, height: 104, child: image == null || image.url.isEmpty ? Container(color: primary.withAlpha(20), child: Icon(Icons.restaurant_rounded, color: primary, size: 34)) : Image.network(AppConfig.assetUrl(image.url), fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(color: primary.withAlpha(20), child: Icon(Icons.restaurant_rounded, color: primary, size: 34))))),
        Transform.translate(offset: const Offset(0, -14), child: OutlinedButton(onPressed: () => context.push('/product/${product.uuid}'), style: OutlinedButton.styleFrom(backgroundColor: Colors.white, foregroundColor: primary, side: BorderSide(color: primary), minimumSize: const Size(78, 34), padding: EdgeInsets.zero, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9))), child: const Text('ADD', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800)))),
      ])),
    ])));
  }
}

class _EmptyCatalog extends StatelessWidget {
  final Future<void> Function() onRetry;
  const _EmptyCatalog({required this.onRetry});
  @override
  Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.restaurant_menu_rounded, size: 54, color: Colors.grey.shade400), const SizedBox(height: 12), const Text('Nothing matching that search', style: TextStyle(fontWeight: FontWeight.w800)), const SizedBox(height: 5), TextButton(onPressed: onRetry, child: const Text('Refresh menu'))]));
}
