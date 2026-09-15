import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../services/api_client.dart';
import '../../app/providers/cart_provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../app/providers/location_provider.dart';
import '../../models/address.dart';
import '../../widgets/price_text.dart';
import '../../widgets/state_widgets.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _orderType = 'delivery';
  String _paymentMethod = 'cod';
  Address? _selectedAddress;
  bool _placing = false;
  String? _error;
  final _notesController = TextEditingController();

  // Serviceability state
  bool _validatingAddress = false;
  bool _addressServiceable = true;
  String? _serviceabilityWarning;
  int? _deliveryFeeFromValidation;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final store = context.read<BootstrapProvider>();
      if (!store.deliveryEnabled && store.pickupEnabled && mounted) {
        setState(() => _orderType = 'pickup');
      }
      _loadSelectedDeliveryAddress();
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _validateServiceability() async {
    if (_orderType != 'delivery' || _selectedAddress == null) {
      setState(() {
        _addressServiceable = true;
        _serviceabilityWarning = null;
        _deliveryFeeFromValidation = null;
      });
      return;
    }

    setState(() {
      _validatingAddress = true;
      _serviceabilityWarning = null;
    });

    try {
      final response = await ApiClient().post(
        '/customer/checkout/validate',
        data: {'address_uuid': _selectedAddress!.uuid},
      );
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final d = data['data'];
        final serviceable = d['serviceable'] == true;
        setState(() {
          _addressServiceable = serviceable;
          _serviceabilityWarning =
              serviceable ? null : 'This address is outside the delivery area';
          _deliveryFeeFromValidation = (d['delivery_fee'] as num?)?.toInt();
        });
      }
    } on DioException catch (error) {
      final body = error.response?.data;
      final apiError =
          body is Map && body['error'] is Map ? body['error'] as Map : null;
      setState(() {
        _addressServiceable = false;
        _serviceabilityWarning = apiError?['message']?.toString() ??
            'This address is outside the delivery area';
      });
    } catch (_) {
      // If validation call fails, allow proceeding — server will recheck at order time.
    } finally {
      setState(() => _validatingAddress = false);
    }
  }

  Future<void> _loadSelectedDeliveryAddress() async {
    try {
      final response = await ApiClient().get('/customer/addresses');
      final body = response.data;
      if (body is! Map || body['success'] != true || body['data'] is! List) {
        return;
      }
      final addresses = (body['data'] as List)
          .whereType<Map>()
          .map((item) => Address.fromJson(Map<String, dynamic>.from(item)))
          .toList();
      if (addresses.isEmpty) return;

      if (!mounted) return;
      final activeUuid = context.read<LocationProvider>().activeAddressUuid;
      Address? address;
      for (final item in addresses) {
        if (item.uuid == activeUuid) {
          address = item;
          break;
        }
      }
      for (final item in addresses) {
        if (address == null && item.isDefault) address = item;
      }
      address ??= addresses.first;
      if (!mounted) return;
      setState(() => _selectedAddress = address);
      _validateServiceability();
    } catch (_) {
      // Checkout will show a clear address requirement if the API is offline.
    }
  }

  Future<void> _placeOrder() async {
    final store = context.read<BootstrapProvider>();
    final cart = context.read<CartProvider>();
    if (!store.isAcceptingOrders) return;
    if (cart.subtotal < store.minOrderAmount) {
      setState(() => _error =
          'Minimum order value is ${PriceText.format(store.minOrderAmount)}');
      return;
    }
    if (_orderType == 'delivery' && _selectedAddress == null) {
      setState(() => _error = 'Please select a delivery address');
      return;
    }

    setState(() {
      _placing = true;
      _error = null;
    });

    try {
      final payload = <String, dynamic>{
        'order_type': _orderType,
        'payment_method': _paymentMethod,
      };
      if (_selectedAddress != null) {
        payload['address_uuid'] = _selectedAddress!.uuid;
      }
      if (_notesController.text.trim().isNotEmpty) {
        payload['notes'] = _notesController.text.trim();
      }

      final response =
          await ApiClient().post('/customer/checkout', data: payload);
      final data = response.data;

      if (data['success'] == true) {
        if (mounted) {
          context.read<CartProvider>().loadCart();

          final orderUuid = data['data']?['order']?['uuid'] as String?;
          final payment = data['data']?['payment'] as Map<String, dynamic>?;

          if (payment != null && payment['razorpay_order_id'] != null) {
            // Online payment order created - show payment info dialog
            if (mounted) {
              showDialog(
                context: context,
                barrierDismissible: false,
                builder: (ctx) => AlertDialog(
                  title: const Text('Complete Payment'),
                  content: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                          'Your order has been placed. Please complete payment to confirm.'),
                      const SizedBox(height: 16),
                      Text('Order ID: ${payment['razorpay_order_id']}',
                          style: const TextStyle(
                              fontSize: 12, color: Colors.black54)),
                      Text(
                          'Amount: ${PriceText.format((payment['amount'] as num).toInt())}',
                          style: const TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 12),
                      const Text(
                        'Redirecting to payment gateway...',
                        style: TextStyle(
                            color: Colors.blue, fontStyle: FontStyle.italic),
                      ),
                    ],
                  ),
                  actions: [
                    TextButton(
                      onPressed: () {
                        Navigator.of(ctx).pop();
                        if (orderUuid != null) {
                          context.go('/order/$orderUuid');
                        } else {
                          context.go('/home');
                        }
                      },
                      child: const Text('View Order'),
                    ),
                  ],
                ),
              );
            }
            return;
          }

          await _showOrderPlacedCelebration();
          if (!mounted) return;

          if (orderUuid != null) {
            context.go('/order/$orderUuid');
          } else {
            context.go('/home');
          }
        }
      } else {
        setState(() =>
            _error = data['error']?['message'] ?? 'Failed to place order');
      }
    } on DioException catch (error) {
      final body = error.response?.data;
      final apiError =
          body is Map && body['error'] is Map ? body['error'] as Map : null;
      setState(() =>
          _error = apiError?['message']?.toString() ?? 'Failed to place order');
    } catch (_) {
      setState(() => _error = 'Failed to place order. Please try again.');
    } finally {
      setState(() => _placing = false);
    }
  }

  Future<void> _showOrderPlacedCelebration() {
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) => Dialog(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 30),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TweenAnimationBuilder<double>(
              tween: Tween(begin: 0.55, end: 1),
              duration: const Duration(milliseconds: 550),
              curve: Curves.elasticOut,
              builder: (_, scale, child) => Transform.scale(scale: scale, child: child),
              child: const CircleAvatar(
                radius: 38,
                backgroundColor: Color(0x1AE23744),
                child: Icon(Icons.check_circle_rounded, color: Color(0xFFE23744), size: 58),
              ),
            ),
            const SizedBox(height: 18),
            const Text('Order placed!', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            const Text('The store has received your order. We’ll keep you updated at every step.', textAlign: TextAlign.center),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Track order'),
            ),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final bootstrap = context.watch<BootstrapProvider>();

    final fulfilmentOptions = <ButtonSegment<String>>[
      if (bootstrap.deliveryEnabled)
        const ButtonSegment(
            value: 'delivery',
            icon: Icon(Icons.delivery_dining),
            label: Text('Delivery')),
      if (bootstrap.pickupEnabled)
        const ButtonSegment(
            value: 'pickup',
            icon: Icon(Icons.storefront),
            label: Text('Pickup')),
    ];

    final deliveryCharge = _orderType == 'delivery'
        ? (_deliveryFeeFromValidation ?? bootstrap.deliveryChargeFixed)
        : 0;
    final serviceCharge = bootstrap.serviceChargePercent > 0
        ? (cart.subtotal * bootstrap.serviceChargePercent / 100).round()
        : 0;
    final taxAmount = bootstrap.taxRate > 0
        ? (cart.subtotal * bootstrap.taxRate / 100).round()
        : 0;
    final estimatedTotal =
        cart.subtotal + deliveryCharge + serviceCharge + taxAmount;

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (!bootstrap.isAcceptingOrders) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade200,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Row(children: [
                      Icon(Icons.schedule_rounded, color: Colors.grey.shade700),
                      const SizedBox(width: 8),
                      Expanded(child: Text(bootstrap.orderingMessage,
                          style: TextStyle(color: Colors.grey.shade800))),
                    ]),
                  ),
                  const SizedBox(height: 16),
                ],
                if (_error != null) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(_error!,
                        style: TextStyle(color: Colors.red.shade700)),
                  ),
                  const SizedBox(height: 16),
                ],
                if (bootstrap.minOrderAmount > 0 &&
                    cart.subtotal < bootstrap.minOrderAmount) ...[
                  Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                          color: Colors.orange.shade50,
                          borderRadius: BorderRadius.circular(12)),
                      child: Text(
                          'Add ${PriceText.format(bootstrap.minOrderAmount - cart.subtotal)} more to reach the store minimum.',
                          style: TextStyle(color: Colors.orange.shade900))),
                  const SizedBox(height: 16),
                ],
                if (_serviceabilityWarning != null) ...[
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.orange.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.orange.shade200),
                    ),
                    child: Row(
                      children: [
                        Icon(Icons.warning_amber_rounded,
                            color: Colors.orange.shade800, size: 20),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _serviceabilityWarning!,
                            style: TextStyle(color: Colors.orange.shade900),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Order type
                _sectionTitle(context, '1. Fulfilment',
                    'Choose delivery or store pickup'),
                const SizedBox(height: 8),
                if (fulfilmentOptions.isEmpty)
                  const Text('This store is not accepting orders right now.')
                else
                  SegmentedButton<String>(
                    segments: fulfilmentOptions,
                    selected: {_orderType},
                    onSelectionChanged: bootstrap.isAcceptingOrders && !_validatingAddress
                        ? (s) {
                            setState(() => _orderType = s.first);
                            _validateServiceability();
                          }
                        : null,
                  ),

                // Address (for delivery)
                if (_orderType == 'delivery') ...[
                  const SizedBox(height: 24),
                  _sectionTitle(context, '2. Delivery address',
                      'Where should we bring your order?'),
                  const SizedBox(height: 8),
                  Card(
                    child: ListTile(
                      onTap: () async {
                        final result = await context.push('/addresses/select');
                        if (result != null && result is Address && mounted) {
                          setState(() => _selectedAddress = result);
                          _validateServiceability();
                        }
                      },
                      leading: Icon(
                        _selectedAddress != null
                            ? Icons.location_on
                            : Icons.add_location,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                      title: Text(
                        _selectedAddress?.label ?? 'Select Address',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: _selectedAddress != null
                          ? Text(_selectedAddress!.fullAddress,
                              maxLines: 2, overflow: TextOverflow.ellipsis)
                          : const Text(
                              'Tap to choose a delivery address'),
                      trailing: const Icon(Icons.chevron_right),
                    ),
                  ),
                ],

                // Payment method
                const SizedBox(height: 24),
                _sectionTitle(
                    context,
                    _orderType == 'delivery' ? '3. Payment' : '2. Payment',
                    'Choose how you would like to pay'),
                const SizedBox(height: 8),
                Card(
                  child: Column(
                    children: [
                      RadioListTile<String>(
                        value: 'cod',
                        groupValue: _paymentMethod,
                        onChanged: bootstrap.paymentMethods.contains('cod')
                            ? (value) => setState(() => _paymentMethod = value!)
                            : null,
                        title: Text(_orderType == 'pickup'
                            ? 'Pay at store'
                            : 'Cash on Delivery'),
                        secondary: const Icon(Icons.payments_outlined),
                      ),
                      if (bootstrap.paymentMethods.contains('online'))
                        RadioListTile<String>(
                          value: 'online',
                          groupValue: _paymentMethod,
                          onChanged: (value) =>
                              setState(() => _paymentMethod = value!),
                          title: const Text('Pay Online'),
                          subtitle: const Text(
                            'UPI, Cards, Net Banking',
                            style: TextStyle(fontSize: 12),
                          ),
                          secondary: const Icon(Icons.credit_card),
                        ),
                    ],
                  ),
                ),

                // Notes
                const SizedBox(height: 24),
                _sectionTitle(context, 'Order notes',
                    'Optional instructions for the store'),
                const SizedBox(height: 8),
                TextField(
                  controller: _notesController,
                  maxLines: 3,
                  decoration: const InputDecoration(
                    hintText: 'Any special instructions...',
                    border: OutlineInputBorder(),
                  ),
                ),

                // Summary
                const SizedBox(height: 24),
                _sectionTitle(context, 'Review order',
                    'Your final total will include any applicable delivery fee, tax, or offer.'),
                const SizedBox(height: 8),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      children: [
                        ...cart.items.map((item) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Expanded(
                                    child: Text(
                                      '${item.quantity}x ${item.productName}',
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  Text(PriceText.format(item.lineTotal)),
                                ],
                              ),
                            )),
                        const Divider(),
                        _summaryRow('Subtotal', cart.subtotal),
                        if (deliveryCharge > 0)
                          _summaryRow('Delivery charge', deliveryCharge),
                        if (serviceCharge > 0)
                          _summaryRow(
                              'Service charge (${bootstrap.serviceChargePercent}%)',
                              serviceCharge),
                        if (taxAmount > 0)
                          _summaryRow('Tax (${bootstrap.taxRate}%)', taxAmount),
                        if (deliveryCharge > 0 ||
                            serviceCharge > 0 ||
                            taxAmount > 0) ...[
                          const Divider(),
                          _summaryRow('Estimated Total', estimatedTotal,
                              bold: true),
                        ],
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: _placing ||
                            !bootstrap.isAcceptingOrders ||
                            _validatingAddress ||
                            (!_addressServiceable &&
                                _orderType == 'delivery') ||
                            fulfilmentOptions.isEmpty ||
                            !bootstrap.paymentMethods.contains(
                                _paymentMethod == 'online'
                                    ? 'online'
                                    : 'cod') ||
                            cart.subtotal < bootstrap.minOrderAmount
                        ? null
                        : _placeOrder,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                    child: _placing || _validatingAddress
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : Text(
                            'Place Order - ${PriceText.format(estimatedTotal)}',
                            style: const TextStyle(fontSize: 16),
                          ),
                  ),
                ),
                const SizedBox(height: 32),
              ],
            ),
          ),
          if (_placing)
            Container(
              color: Colors.black.withAlpha(80),
              child: const LoadingStateWidget(message: 'Placing your order...'),
            ),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, int paise, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label,
              style: TextStyle(
                  fontWeight: bold ? FontWeight.bold : FontWeight.normal,
                  fontSize: bold ? 16 : 14)),
          Text(PriceText.format(paise),
              style: TextStyle(
                  fontWeight: bold ? FontWeight.bold : FontWeight.normal,
                  fontSize: bold ? 16 : 14)),
        ],
      ),
    );
  }

  Widget _sectionTitle(BuildContext context, String title, String description) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title,
            style: Theme.of(context)
                .textTheme
                .titleMedium
                ?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 2),
        Text(description,
            style: TextStyle(color: Colors.grey[600], fontSize: 12)),
        const SizedBox(height: 8),
      ],
    );
  }
}
