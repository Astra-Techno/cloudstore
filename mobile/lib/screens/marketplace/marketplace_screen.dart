import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../app/providers/auth_provider.dart';
import '../../services/api_client.dart';

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key});

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  List<Map<String, dynamic>> _stores = [];
  bool _loading = true;
  String _query = '';

  @override
  void initState() {
    super.initState();
    _loadStores();
  }

  Future<void> _loadStores() async {
    setState(() => _loading = true);
    try {
      final response = await ApiClient().get('/marketplace/stores', queryParameters: _query.isEmpty ? null : {'q': _query});
      final data = response.data;
      if (data['success'] == true && data['data'] is List) {
        _stores = (data['data'] as List).map((item) => Map<String, dynamic>.from(item)).toList();
      }
    } catch (_) {
      _stores = [];
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openStore(Map<String, dynamic> store) async {
    // Customer records deliberately remain merchant-owned. A token for Store A
    // must not bleed into Store B when a shopper changes marketplace stores.
    await context.read<AuthProvider>().logout();
    ApiClient().setMarketplaceStore(store['slug'] as String);
    if (!mounted) return;
    context.go('/home');
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;
    return Scaffold(
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('CloudMarket', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                const SizedBox(height: 4),
                const Text('Order directly from local stores near you.'),
                const SizedBox(height: 18),
                TextField(
                  onChanged: (value) => _query = value,
                  onSubmitted: (_) => _loadStores(),
                  decoration: InputDecoration(
                    hintText: 'Search restaurants, kitchens, bakeries...',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(icon: const Icon(Icons.tune), onPressed: _loadStores),
                    filled: true,
                    fillColor: const Color(0xFFF4F4F5),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none),
                  ),
                ),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
              child: Text('Stores on CloudMarket', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
            ),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator())
                  : _stores.isEmpty
                      ? const Center(child: Text('No stores are available yet.'))
                      : RefreshIndicator(
                          onRefresh: _loadStores,
                          child: ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                            itemCount: _stores.length,
                            separatorBuilder: (_, __) => const SizedBox(height: 12),
                            itemBuilder: (context, index) {
                              final store = _stores[index];
                              final logo = store['logo_url'] as String?;
                              return Card(
                                clipBehavior: Clip.antiAlias,
                                child: InkWell(
                                  onTap: () => _openStore(store),
                                  child: Padding(
                                    padding: const EdgeInsets.all(14),
                                    child: Row(children: [
                                      Container(
                                        width: 68, height: 68,
                                        decoration: BoxDecoration(color: primary.withAlpha(22), borderRadius: BorderRadius.circular(14)),
                                        child: logo != null && logo.isNotEmpty
                                            ? ClipRRect(borderRadius: BorderRadius.circular(14), child: Image.network(logo, fit: BoxFit.cover))
                                            : Icon(Icons.storefront_rounded, color: primary, size: 32),
                                      ),
                                      const SizedBox(width: 14),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text(store['name'] as String? ?? 'Store', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
                                        const SizedBox(height: 4),
                                        Text((store['business_type'] as String? ?? 'local store').replaceAll('_', ' · '), style: const TextStyle(fontSize: 12)),
                                        const SizedBox(height: 5),
                                        Text('${store['product_count'] ?? 0} items · Self delivery or pickup', style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
                                      ])),
                                      const Icon(Icons.chevron_right),
                                    ]),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
            ),
          ],
        ),
      ),
    );
  }
}
