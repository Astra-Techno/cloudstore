import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../services/api_client.dart';
import '../../models/order.dart';
import '../../widgets/price_text.dart';
import '../../widgets/status_badge.dart';

class OrderDetailScreen extends StatefulWidget {
  final String uuid;
  const OrderDetailScreen({super.key, required this.uuid});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Order? _order;
  List<OrderItem> _items = [];
  List<StatusHistoryEntry> _history = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadOrder();
  }

  Future<void> _loadOrder() async {
    try {
      final response = await ApiClient().get('/customer/orders/${widget.uuid}');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final d = data['data'];
        setState(() {
          _order = Order.fromJson(d['order']);
          _items = (d['items'] as List<dynamic>?)
                  ?.map((i) => OrderItem.fromJson(i))
                  .toList() ??
              [];
          _history = (d['status_history'] as List<dynamic>?)
                  ?.map((h) => StatusHistoryEntry.fromJson(h))
                  .toList() ??
              [];
        });
      }
    } catch (_) {}
    setState(() => _loading = false);
  }

  Map<String, dynamic> _parseSnapshot(String? json) {
    if (json == null) return {};
    try {
      return jsonDecode(json);
    } catch (_) {
      return {};
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(_order?.orderNumber ?? 'Order')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _order == null
              ? const Center(child: Text('Order not found'))
              : RefreshIndicator(
                  onRefresh: _loadOrder,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Status card
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      _order!.orderNumber,
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      DateFormat('dd MMM yyyy, hh:mm a')
                                          .format(DateTime.parse(_order!.createdAt)),
                                      style: TextStyle(color: Colors.grey[600], fontSize: 13),
                                    ),
                                  ],
                                ),
                                StatusBadge(status: _order!.status, fontSize: 14),
                              ],
                            ),
                          ),
                        ),

                        // Order info
                        const SizedBox(height: 16),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              children: [
                                _infoRow('Type', _order!.orderType.replaceAll('_', ' ')),
                                _infoRow('Payment', _order!.paymentMethod.replaceAll('_', ' ')),
                                _infoRow('Payment Status', _order!.paymentStatus),
                              ],
                            ),
                          ),
                        ),

                        if (_order!.orderType == 'delivery' && _parseSnapshot(_order!.addressSnapshot).isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text('Delivery address', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Card(child: Padding(padding: const EdgeInsets.all(16), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Icon(Icons.location_on_outlined, color: Theme.of(context).colorScheme.primary),
                            const SizedBox(width: 10),
                            Expanded(child: Text([
                              _parseSnapshot(_order!.addressSnapshot)['address_line_1'],
                              _parseSnapshot(_order!.addressSnapshot)['address_line_2'],
                              _parseSnapshot(_order!.addressSnapshot)['landmark'],
                              _parseSnapshot(_order!.addressSnapshot)['city'],
                              _parseSnapshot(_order!.addressSnapshot)['postal_code'],
                            ].where((part) => part != null && part.toString().trim().isNotEmpty).join(', '))),
                          ]))),
                        ],

                        if (_order!.notes?.trim().isNotEmpty == true) ...[
                          const SizedBox(height: 16),
                          Text('Your instructions', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Card(child: Padding(padding: const EdgeInsets.all(16), child: Text(_order!.notes!))),
                        ],

                        // Items
                        const SizedBox(height: 16),
                        Text('Items', style: Theme.of(context).textTheme.titleMedium),
                        const SizedBox(height: 8),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Column(
                              children: [
                                ..._items.map((item) {
                                  final product = _parseSnapshot(item.productSnapshot);
                                  final variant = _parseSnapshot(item.variantSnapshot);
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 12),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                product['name']?.toString() ?? 'Item',
                                                style: const TextStyle(fontWeight: FontWeight.w600),
                                              ),
                                              if (variant.isNotEmpty && variant['name'] != null)
                                                Text(
                                                  variant['name'].toString(),
                                                  style: TextStyle(color: Colors.grey[600], fontSize: 13),
                                                ),
                                              Text(
                                                '${item.quantity} x ${PriceText.format(item.unitPrice)}',
                                                style: TextStyle(color: Colors.grey[500], fontSize: 13),
                                              ),
                                            ],
                                          ),
                                        ),
                                        Text(
                                          PriceText.format(item.lineTotal),
                                          style: const TextStyle(fontWeight: FontWeight.w600),
                                        ),
                                      ],
                                    ),
                                  );
                                }),
                                const Divider(),
                                _priceRow('Subtotal', _order!.subtotal),
                                if (_order!.deliveryFee > 0) _priceRow('Delivery Fee', _order!.deliveryFee),
                                if (_order!.taxAmount > 0) _priceRow('Tax', _order!.taxAmount),
                                if (_order!.discountAmount > 0)
                                  _priceRow('Discount', -_order!.discountAmount),
                                const Divider(),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text(
                                      'Total',
                                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                                    ),
                                    Text(
                                      PriceText.format(_order!.total),
                                      style: TextStyle(
                                        fontWeight: FontWeight.bold,
                                        fontSize: 18,
                                        color: Theme.of(context).colorScheme.primary,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),

                        // Status timeline
                        if (_history.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text('Order Timeline', style: Theme.of(context).textTheme.titleMedium),
                          const SizedBox(height: 8),
                          Card(
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                children: _history.asMap().entries.map((entry) {
                                  final h = entry.value;
                                  final isLast = entry.key == _history.length - 1;
                                  return Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Column(
                                        children: [
                                          Container(
                                            width: 12,
                                            height: 12,
                                            decoration: BoxDecoration(
                                              shape: BoxShape.circle,
                                              color: isLast
                                                  ? Theme.of(context).colorScheme.primary
                                                  : Colors.grey[400],
                                            ),
                                          ),
                                          if (!isLast)
                                            Container(
                                              width: 2,
                                              height: 40,
                                              color: Colors.grey[300],
                                            ),
                                        ],
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Padding(
                                          padding: const EdgeInsets.only(bottom: 16),
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                h.toStatus.replaceAll('_', ' ').toUpperCase(),
                                                style: TextStyle(
                                                  fontWeight: FontWeight.w600,
                                                  fontSize: 13,
                                                  color: isLast
                                                      ? Theme.of(context).colorScheme.primary
                                                      : Colors.grey[700],
                                                ),
                                              ),
                                              const SizedBox(height: 2),
                                              Text(
                                                DateFormat('dd MMM, hh:mm a')
                                                    .format(DateTime.parse(h.createdAt)),
                                                style: TextStyle(color: Colors.grey[500], fontSize: 12),
                                              ),
                                              if (h.notes != null)
                                                Padding(
                                                  padding: const EdgeInsets.only(top: 2),
                                                  child: Text(
                                                    h.notes!,
                                                    style: TextStyle(color: Colors.grey[500], fontSize: 12),
                                                  ),
                                                ),
                                            ],
                                          ),
                                        ),
                                      ),
                                    ],
                                  );
                                }).toList(),
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),
                ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey[600])),
          Text(
            value[0].toUpperCase() + value.substring(1),
            style: const TextStyle(fontWeight: FontWeight.w500),
          ),
        ],
      ),
    );
  }

  Widget _priceRow(String label, int paise) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 14)),
          Text(
            paise < 0 ? '-${PriceText.format(-paise)}' : PriceText.format(paise),
            style: TextStyle(
              fontSize: 14,
              color: paise < 0 ? Colors.green : null,
            ),
          ),
        ],
      ),
    );
  }
}
