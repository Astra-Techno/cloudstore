import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../../services/api_client.dart';
import '../../app/providers/cart_provider.dart';
import '../../app/providers/bootstrap_provider.dart';
import '../../models/address.dart';
import '../../widgets/price_text.dart';

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

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final store = context.read<BootstrapProvider>();
      if (!store.deliveryEnabled && store.pickupEnabled && mounted) {
        setState(() => _orderType = 'pickup');
      }
    });
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _selectAddress() async {
    final result = await context.push<Address>('/addresses/select');
    if (result != null) {
      setState(() => _selectedAddress = result);
    }
  }

  Future<void> _placeOrder() async {
    final store = context.read<BootstrapProvider>();
    final cart = context.read<CartProvider>();
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

          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Order placed successfully!')),
          );

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
    } catch (_) {
      setState(() => _error = 'Failed to place order');
    } finally {
      setState(() => _placing = false);
    }
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

    final deliveryCharge =
        _orderType == 'delivery' ? bootstrap.deliveryChargeFixed : 0;
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
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (_error != null) ...[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  borderRadius: BorderRadius.circular(8),
                ),
                child:
                    Text(_error!, style: TextStyle(color: Colors.red.shade700)),
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

            // Order type
            _sectionTitle(
                context, '1. Fulfilment', 'Choose delivery or store pickup'),
            const SizedBox(height: 8),
            if (fulfilmentOptions.isEmpty)
              const Text('This store is not accepting orders right now.')
            else
              SegmentedButton<String>(
                segments: fulfilmentOptions,
                selected: {_orderType},
                onSelectionChanged: (s) => setState(() => _orderType = s.first),
              ),

            // Address (for delivery)
            if (_orderType == 'delivery') ...[
              const SizedBox(height: 24),
              _sectionTitle(context, '2. Delivery address',
                  'Where should we bring your order?'),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
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
                      : const Text('Tap to choose delivery address'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: _selectAddress,
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
                    const Padding(
                      padding: EdgeInsets.fromLTRB(16, 0, 16, 12),
                      child: Text(
                        'Online payments will appear here once this shop connects its payment gateway.',
                        style: TextStyle(fontSize: 12, color: Colors.black54),
                      ),
                    ),
                ],
              ),
            ),

            // Notes
            const SizedBox(height: 24),
            _sectionTitle(
                context, 'Order notes', 'Optional instructions for the store'),
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
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
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
                        fulfilmentOptions.isEmpty ||
                        !bootstrap.paymentMethods.contains('cod') ||
                        cart.subtotal < bootstrap.minOrderAmount
                    ? null
                    : _placeOrder,
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 16),
                ),
                child: _placing
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
