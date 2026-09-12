import 'package:flutter/material.dart';
import '../../models/cart_models.dart';
import '../../services/api_client.dart';

class CartProvider extends ChangeNotifier {
  List<CartItem> _items = [];
  int _subtotal = 0;
  bool _isLoading = false;
  String? _error;

  List<CartItem> get items => List.unmodifiable(_items);
  int get itemCount => _items.fold(0, (total, item) => total + item.quantity);
  int get subtotal => _subtotal;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _items.isEmpty;

  Future<void> loadCart() async {
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiClient().get('/customer/cart');
      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        final cart = CartData.fromJson(data['data']);
        _items = cart.items;
        _subtotal = cart.subtotal;
      }
      _error = null;
    } catch (_) {
      _error = 'Unable to refresh your cart. Check your connection and try again.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> addItem({required String productUuid, String? variantUuid, int quantity = 1, List<int>? addonIds}) async {
    _error = null;
    try {
      final payload = <String, dynamic>{'product_uuid': productUuid, 'quantity': quantity};
      if (variantUuid != null) payload['variant_uuid'] = variantUuid;
      if (addonIds != null && addonIds.isNotEmpty) payload['addon_ids'] = addonIds;
      final response = await ApiClient().post('/customer/cart/items', data: payload);
      if (response.data['success'] == true) {
        await loadCart();
        return true;
      }
      _error = response.data['error']?['message'] ?? 'Unable to add this item.';
    } catch (_) {
      _error = 'Unable to add this item. Please try again.';
    }
    notifyListeners();
    return false;
  }

  Future<bool> updateItem(int itemId, int quantity) async {
    _error = null;
    try {
      final response = await ApiClient().patch('/customer/cart/items/$itemId', data: {'quantity': quantity});
      if (response.data['success'] == true) {
        await loadCart();
        return true;
      }
      _error = response.data['error']?['message'] ?? 'Unable to update this item.';
    } catch (_) {
      _error = 'Unable to update this item. Please try again.';
    }
    notifyListeners();
    return false;
  }

  Future<bool> removeItem(int itemId) async {
    _error = null;
    try {
      final response = await ApiClient().delete('/customer/cart/items/$itemId');
      if (response.data['success'] == true) {
        await loadCart();
        return true;
      }
      _error = response.data['error']?['message'] ?? 'Unable to remove this item.';
    } catch (_) {
      _error = 'Unable to remove this item. Please try again.';
    }
    notifyListeners();
    return false;
  }

  Future<bool> clearCart() async {
    try {
      final response = await ApiClient().delete('/customer/cart');
      if (response.data['success'] == true) {
        _items = [];
        _subtotal = 0;
        _error = null;
        notifyListeners();
        return true;
      }
    } catch (_) {
      _error = 'Unable to clear your cart. Please try again.';
      notifyListeners();
    }
    return false;
  }
}
