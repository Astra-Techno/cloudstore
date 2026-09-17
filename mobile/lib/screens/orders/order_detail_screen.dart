import 'dart:convert';
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:intl/intl.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../services/api_client.dart';
import '../../app/providers/cart_provider.dart';
import '../../models/order.dart';
import '../../models/json_value.dart';
import '../../widgets/price_text.dart';
import '../../widgets/status_badge.dart';
import '../../widgets/state_widgets.dart';
import '../../services/device_actions.dart';

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
  Map<String, dynamic>? _driver;
  bool _loading = true;
  String? _error;
  Timer? _refreshTimer;

  @override
  void initState() {
    super.initState();
    _loadOrder();
    _refreshTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      if (_isActiveOrder) _loadOrder(background: true);
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadOrder({bool background = false}) async {
    try {
      final response = await ApiClient().get('/customer/orders/${widget.uuid}');
      final data = response.data;
      final d = data is Map && data['data'] is Map
          ? Map<String, dynamic>.from(data['data'] as Map)
          : null;
      final orderJson = d == null ? null : JsonValue.object(d['order']);
      if (data is Map && data['success'] == true && d != null && orderJson != null) {
        if (!mounted) return;
        setState(() {
          _order = Order.fromJson(orderJson);
          _items = JsonValue.objectList(d['items']).map(OrderItem.fromJson).toList();
          _history = JsonValue.objectList(d['status_history'])
              .map(StatusHistoryEntry.fromJson)
              .toList();
          _driver = d['driver'] is Map
              ? Map<String, dynamic>.from(d['driver'] as Map)
              : null;
          _error = null;
        });
      } else if (mounted) {
        final message = data is Map && data['error'] is Map
            ? data['error']['message']?.toString()
            : null;
        setState(() => _error = message ?? 'Order not found');
      }
    } catch (_) {
      if (!mounted) return;
      // Preserve an already-visible order during a background refresh; a
      // momentary network loss must not hide tracking information.
      if (background && _order != null) return;
      setState(() => _error = 'Unable to load order details.');
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _cancelOrder() async {
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Cancel Order'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Are you sure you want to cancel this order?'),
            const SizedBox(height: 16),
            TextField(
              controller: reasonController,
              maxLines: 2,
              decoration: const InputDecoration(
                hintText: 'Reason for cancellation (optional)',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('No, Keep It'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: TextButton.styleFrom(foregroundColor: Colors.red),
            child: const Text('Yes, Cancel'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      reasonController.dispose();
      return;
    }

    try {
      final payload = <String, dynamic>{};
      if (reasonController.text.trim().isNotEmpty) {
        payload['reason'] = reasonController.text.trim();
      }
      reasonController.dispose();

      final response = await ApiClient().post(
        '/customer/orders/${widget.uuid}/cancel',
        data: payload,
      );
      final data = response.data;
      if (data['success'] == true) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Order cancelled')),
          );
          await _loadOrder();
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(
                  data['error']?['message'] ?? 'Failed to cancel order'),
            ),
          );
        }
      }
    } on DioException catch (error) {
      final body = error.response?.data;
      final apiError =
          body is Map && body['error'] is Map ? body['error'] as Map : null;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                apiError?['message']?.toString() ?? 'Failed to cancel order'),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to cancel order')),
        );
      }
    }
  }

  Future<void> _reorder() async {
    try {
      final response = await ApiClient().post(
        '/customer/orders/${widget.uuid}/reorder',
      );
      final data = response.data;
      if (data['success'] == true && mounted) {
        context.read<CartProvider>().loadCart();
        final added = (data['data']?['added'] as num?)?.toInt() ?? 0;
        final skipped = (data['data']?['skipped'] as num?)?.toInt() ?? 0;
        final message = skipped > 0
            ? '$added item(s) added to cart, $skipped skipped (unavailable)'
            : '$added item(s) added to cart';
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(message)),
        );
        context.go('/home');
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content:
                Text(data['error']?['message'] ?? 'Failed to reorder'),
          ),
        );
      }
    } on DioException catch (error) {
      final body = error.response?.data;
      final apiError =
          body is Map && body['error'] is Map ? body['error'] as Map : null;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
                apiError?['message']?.toString() ?? 'Failed to reorder'),
          ),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to reorder')),
        );
      }
    }
  }

  Map<String, dynamic> _parseSnapshot(String? json) {
    if (json == null) return {};
    try {
      return jsonDecode(json);
    } catch (_) {
      return {};
    }
  }

  /// Build a map of status -> formatted timestamp from the status history.
  Map<String, String?> _buildStatusTimestamps() {
    final timestamps = <String, String?>{};
    for (final h in _history) {
      try {
        timestamps[h.toStatus] =
            DateFormat('dd MMM, hh:mm a').format(DateTime.parse(h.createdAt));
      } catch (_) {
        timestamps[h.toStatus] = null;
      }
    }
    return timestamps;
  }

  bool get _isActiveOrder {
    final s = _order?.status;
    return s == 'pending_payment' ||
        s == 'payment_processing' ||
        s == 'confirmed' ||
        s == 'accepted' ||
        s == 'preparing' ||
        s == 'ready' ||
        s == 'ready_for_pickup' ||
        s == 'out_for_delivery';
  }

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.primary;

    return Scaffold(
      appBar: AppBar(title: Text(_order?.orderNumber ?? 'Order')),
      body: _loading
          ? const LoadingStateWidget(message: 'Loading order details...')
          : _error != null
              ? ErrorStateWidget(message: _error!, onRetry: _loadOrder)
              : _order == null
                  ? const EmptyStateWidget(
                      icon: Icons.receipt_long_outlined,
                      title: 'Order not found',
                      subtitle: 'This order may have been removed or is unavailable.',
                    )
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

                            // Live tracking stepper for active orders
                            if (_isActiveOrder) ...[
                              const SizedBox(height: 16),
                              Text('Order Progress', style: Theme.of(context).textTheme.titleMedium),
                              const SizedBox(height: 12),
                              Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(16),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      // Status message
                                      _buildStatusMessage(),
                                      const SizedBox(height: 16),
                                      // Visual stepper
                                      OrderTrackingStepper(
                                        currentStatus: _order!.status,
                                        statusTimestamps: _buildStatusTimestamps(),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],

                            // Driver info card for out_for_delivery
                            if (_order!.status == 'out_for_delivery') ...[
                              const SizedBox(height: 16),
                              _buildDriverCard(),
                            ],

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
                                Icon(Icons.location_on_outlined, color: primary),
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
                                    if (_order!.serviceCharge > 0) _priceRow('Service Charge', _order!.serviceCharge),
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
                                            color: primary,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            ),

                            // Status timeline (for completed/cancelled orders or as secondary info)
                            if (_history.isNotEmpty && !_isActiveOrder) ...[
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
                                                      ? primary
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
                                                          ? primary
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

                            // Cancel / Reorder buttons
                            if (_order!.status == 'pending_payment' || _order!.status == 'confirmed') ...[
                              const SizedBox(height: 16),
                              SizedBox(
                                width: double.infinity,
                                child: OutlinedButton.icon(
                                  onPressed: _cancelOrder,
                                  icon: const Icon(Icons.cancel_outlined, color: Colors.red),
                                  label: const Text('Cancel Order', style: TextStyle(color: Colors.red)),
                                  style: OutlinedButton.styleFrom(
                                    padding: const EdgeInsets.symmetric(vertical: 14),
                                    side: const BorderSide(color: Colors.red),
                                  ),
                                ),
                              ),
                            ],
                            if (_order!.status == 'delivered' || _order!.status == 'picked_up' || _order!.status == 'cancelled') ...[
                              const SizedBox(height: 16),
                              Row(
                                children: [
                                  Expanded(
                                    child: FilledButton.icon(
                                      onPressed: _reorder,
                                      icon: const Icon(Icons.replay_rounded),
                                      label: const Text('Reorder'),
                                      style: FilledButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(vertical: 14),
                                      ),
                                    ),
                                  ),
                                  if (_order!.status == 'delivered' || _order!.status == 'picked_up') ...[
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: OutlinedButton.icon(
                                        onPressed: _downloadInvoice,
                                        icon: const Icon(Icons.receipt_long_outlined),
                                        label: const Text('Invoice'),
                                        style: OutlinedButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(vertical: 14),
                                        ),
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ],

                            // Live driver tracking
                            if (_driver != null && _isActiveOrder && (_order!.status == 'out_for_delivery')) ...[
                              const SizedBox(height: 16),
                              _buildDriverTrackingCard(),
                            ],
                            const SizedBox(height: 32),
                          ],
                        ),
                      ),
                    ),
    );
  }

  /// Builds a contextual status message banner for the active order.
  Widget _buildStatusMessage() {
    final status = _order!.status;
    final primary = Theme.of(context).colorScheme.primary;
    final primaryContainer = Theme.of(context).colorScheme.primaryContainer;
    IconData icon;
    String message;

    switch (status) {
      case 'pending_payment':
      case 'payment_processing':
        icon = Icons.hourglass_top_rounded;
        message = 'Your order has been placed and is waiting for confirmation.';
      case 'confirmed':
        icon = Icons.thumb_up_alt_rounded;
        message = 'Your order has been confirmed by the store.';
      case 'accepted':
        icon = Icons.thumb_up_alt_rounded;
        message = 'Your order has been accepted and will be prepared shortly.';
      case 'preparing':
        icon = Icons.restaurant_rounded;
        message = 'Your order is being prepared right now!';
      case 'ready':
        icon = Icons.inventory_2_rounded;
        message = 'Your order is packed and ready for the delivery partner.';
      case 'ready_for_pickup':
        icon = Icons.storefront_rounded;
        message = 'Your order is ready! Head to the store to pick it up.';
      case 'out_for_delivery':
        icon = Icons.delivery_dining_rounded;
        message = 'Your order is on its way!';
      default:
        return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: primaryContainer.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: 1),
            duration: Duration(milliseconds: status == 'preparing' ? 1000 : 700),
            curve: Curves.easeInOut,
            builder: (_, progress, child) {
              if (status == 'preparing') {
                return Transform.rotate(angle: progress * 6.283, child: child);
              }
              if (status == 'out_for_delivery') {
                return Transform.translate(
                  offset: Offset((1 - progress) * -18, 0),
                  child: child,
                );
              }
              return Transform.scale(scale: 0.86 + (progress * 0.14), child: child);
            },
            child: Icon(icon, color: primary, size: 22),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: TextStyle(color: primary, fontSize: 13, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }

  /// Builds the driver info card shown during out_for_delivery.
  Widget _buildDriverCard() {
    final primary = Theme.of(context).colorScheme.primary;
    final primaryContainer = Theme.of(context).colorScheme.primaryContainer;
    final driverName = _driver?['name']?.toString() ?? 'Your delivery partner';
    final driverPhone = _driver?['phone']?.toString();
    final latitude = _asDouble(_driver?['latitude']);
    final longitude = _asDouble(_driver?['longitude']);
    final destination = _parseSnapshot(_order?.addressSnapshot);
    final destinationLat = _asDouble(destination['latitude']);
    final destinationLng = _asDouble(destination['longitude']);
    final eta = _driver?['eta_minutes'];

    return Card(
      color: primaryContainer.withValues(alpha: 0.45),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: primaryContainer,
                  child: Icon(Icons.delivery_dining_rounded, color: primary),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Delivery Partner',
                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        driverName,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
                if (driverPhone != null && driverPhone.isNotEmpty)
                  FilledButton.tonalIcon(
                    onPressed: () => _callDriver(driverPhone),
                    icon: const Icon(Icons.call, size: 18),
                    label: const Text('Call'),
                    style: FilledButton.styleFrom(
                      backgroundColor: primaryContainer,
                      foregroundColor: primary,
                    ),
                  ),
              ],
            ),
            if (_driver == null) ...[
              const SizedBox(height: 8),
              Text(
                'A delivery partner will be assigned shortly.',
                style: TextStyle(color: primary, fontSize: 12),
              ),
            ],
            if (latitude != null && longitude != null) ...[
              const SizedBox(height: 14),
              SizedBox(
                height: 190,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(14),
                  child: FlutterMap(
                    options: MapOptions(initialCenter: LatLng(latitude, longitude), initialZoom: 14),
                    children: [
                      TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', userAgentPackageName: 'com.cloudmarket.cloudstore'),
                      MarkerLayer(markers: [
                        Marker(
                          point: LatLng(latitude, longitude),
                          width: 48,
                          height: 48,
                          child: const Icon(Icons.delivery_dining_rounded, color: Colors.red, size: 38),
                        ),
                        if (destinationLat != null && destinationLng != null)
                          Marker(
                            point: LatLng(destinationLat, destinationLng),
                            width: 42,
                            height: 42,
                            child: const Icon(Icons.home_rounded, color: Colors.black87, size: 30),
                          ),
                      ]),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 10),
              Row(children: [
                const Icon(Icons.timer_outlined, size: 18),
                const SizedBox(width: 6),
                Text(
                  eta is num ? 'Estimated arrival in ${eta.toInt()} min' : 'Updating delivery location…',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ]),
              Text('Live location refreshes automatically every 15 seconds.', style: TextStyle(color: Colors.grey[700], fontSize: 12)),
            ],
          ],
        ),
      ),
    );
  }

  double? _asDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '');
  }

  /// Launches the phone dialer for the given number.
  /// Uses url_launcher if available, otherwise shows a dialog with the number.
  Future<void> _callDriver(String phone) async {
    // Since url_launcher is not in pubspec, show a dialog with the phone number
    // so the user can copy/dial manually.
    if (!mounted) return;
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Call Driver'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Contact your delivery partner:'),
            const SizedBox(height: 12),
            SelectableText(
              phone,
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Long press to copy the number',
              style: TextStyle(color: Colors.grey[500], fontSize: 12),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Future<void> _downloadInvoice() async {
    try {
      final response = await ApiClient().get('/customer/orders/${widget.uuid}/invoice');
      final data = response.data;
      if (data['success'] == true && data['data'] != null && mounted) {
        final invoice = Map<String, dynamic>.from(data['data'] as Map);
        _showInvoiceSheet(invoice);
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(data['error']?['message'] ?? 'Failed to load invoice')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Failed to load invoice')),
        );
      }
    }
  }

  void _showInvoiceSheet(Map<String, dynamic> invoice) {
    final items = (invoice['items'] as List?) ?? [];
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.85,
        maxChildSize: 0.95,
        minChildSize: 0.5,
        expand: false,
        builder: (_, scrollController) => SingleChildScrollView(
          controller: scrollController,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(child: Text(
                'INVOICE',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, letterSpacing: 2, color: Theme.of(ctx).colorScheme.primary),
              )),
              const SizedBox(height: 4),
              Center(child: Text(invoice['invoice_number']?.toString() ?? '', style: TextStyle(color: Colors.grey[600], fontSize: 13))),
              const SizedBox(height: 16),
              if (invoice['store'] is Map) ...[
                Text(invoice['store']['name']?.toString() ?? 'Store', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                if (invoice['store']['address'] != null)
                  Text(invoice['store']['address'].toString(), style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                if (invoice['store']['gstin'] != null)
                  Text('GSTIN: ${invoice['store']['gstin']}', style: TextStyle(color: Colors.grey[600], fontSize: 12)),
              ],
              const Divider(height: 24),
              ...items.map((item) {
                final i = Map<String, dynamic>.from(item as Map);
                final qty = (i['quantity'] as num?)?.toInt() ?? 1;
                final total = (i['line_total'] as num?)?.toInt() ?? 0;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(
                    children: [
                      Expanded(child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${i['name'] ?? 'Item'}${i['variant'] != null ? ' (${i['variant']})' : ''}', style: const TextStyle(fontSize: 13)),
                          Text('Qty: $qty', style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                        ],
                      )),
                      Text(PriceText.format(total), style: const TextStyle(fontSize: 13)),
                    ],
                  ),
                );
              }),
              const Divider(),
              _invoiceRow('Subtotal', (invoice['subtotal'] as num?)?.toInt() ?? 0),
              if (((invoice['delivery_fee'] as num?)?.toInt() ?? 0) > 0)
                _invoiceRow('Delivery Fee', (invoice['delivery_fee'] as num).toInt()),
              if (((invoice['service_charge'] as num?)?.toInt() ?? 0) > 0)
                _invoiceRow('Service Charge', (invoice['service_charge'] as num).toInt()),
              if (((invoice['tax_amount'] as num?)?.toInt() ?? 0) > 0)
                _invoiceRow('Tax', (invoice['tax_amount'] as num).toInt()),
              if (((invoice['discount_amount'] as num?)?.toInt() ?? 0) > 0)
                _invoiceRow('Discount', -((invoice['discount_amount'] as num).toInt())),
              const Divider(),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Total', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  Text(PriceText.format((invoice['total'] as num?)?.toInt() ?? 0),
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Theme.of(ctx).colorScheme.primary)),
                ],
              ),
              const SizedBox(height: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Payment: ${(invoice['payment_method'] ?? '').toString().replaceAll('_', ' ')}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                  Text('Order type: ${(invoice['order_type'] ?? '').toString()}',
                    style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _invoiceRow(String label, int paise) {
    final isNegative = paise < 0;
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(color: Colors.grey[600], fontSize: 13)),
          Text(
            isNegative ? '-${PriceText.format(-paise)}' : PriceText.format(paise),
            style: TextStyle(fontSize: 13, color: isNegative ? Colors.green : null),
          ),
        ],
      ),
    );
  }

  Widget _buildDriverTrackingCard() {
    final primary = Theme.of(context).colorScheme.primary;
    final driverName = _driver?['name']?.toString() ?? 'Delivery partner';
    final driverPhone = _driver?['phone']?.toString();
    final eta = (_driver?['eta_minutes'] as num?)?.toInt();
    final distance = (_driver?['distance_km'] as num?)?.toDouble();

    return Card(
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.delivery_dining_rounded, color: primary),
                const SizedBox(width: 8),
                const Text('Live Tracking', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: primary,
                  child: Text(driverName[0].toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(driverName, style: const TextStyle(fontWeight: FontWeight.w600)),
                    if (eta != null)
                      Text('ETA: ~$eta min${distance != null ? ' (${distance.toStringAsFixed(1)} km away)' : ''}',
                        style: TextStyle(color: Colors.grey[700], fontSize: 13)),
                  ],
                )),
                if (driverPhone != null)
                  IconButton(
                    onPressed: () async {
                      final launched = await DeviceActions.call(driverPhone);
                      if (!launched && mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Could not open phone app')),
                        );
                      }
                    },
                    icon: Icon(Icons.call_rounded, color: primary),
                    style: IconButton.styleFrom(backgroundColor: Colors.white),
                  ),
              ],
            ),
          ],
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
            value.isEmpty ? '' : value[0].toUpperCase() + value.substring(1),
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
