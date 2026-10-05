import 'dart:async';
import 'package:flutter/material.dart';
import '../../models/cart_models.dart';
import '../../services/api_client.dart';
import '../../services/cache_service.dart';
import '../../services/api_response.dart';

class CartProvider extends ChangeNotifier {
  static const _cacheKey = 'cart';
  static const _mealSessionCacheKey = 'cart_meal_session';
  static const _cacheTtl = Duration(minutes: 5);

  List<CartItem> _items = [];
  int _subtotal = 0;
  bool _isLoading = false;
  String? _error;
  final Set<int> _updatingItemIds = <int>{};
  String? _mealSessionUuid;
  String? _serviceDate;
  String? _mealSessionName;
  Set<String> _mealSessionProductUuids = <String>{};

  List<CartItem> get items => List.unmodifiable(_items);
  int get itemCount => _items.fold(0, (total, item) => total + item.quantity);
  int get subtotal => _subtotal;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _items.isEmpty;
  bool isUpdatingItem(int itemId) => _updatingItemIds.contains(itemId);
  String? get mealSessionUuid => _mealSessionUuid;
  String? get serviceDate => _serviceDate;
  String? get mealSessionName => _mealSessionName;

  void selectMealSession(
      {required String uuid,
      required String date,
      required String name,
      Iterable<String> productUuids = const []}) {
    _mealSessionUuid = uuid;
    _serviceDate = date;
    _mealSessionName = name;
    _mealSessionProductUuids = productUuids.toSet();
    unawaited(CacheService.put(
      _mealSessionCacheKey,
      {
        'uuid': uuid,
        'date': date,
        'name': name,
        'product_uuids': productUuids.toList(),
      },
      ttl: const Duration(days: 8),
    ));
    notifyListeners();
  }

  void clearMealSessionSelection() {
    _mealSessionUuid = null;
    _serviceDate = null;
    _mealSessionName = null;
    _mealSessionProductUuids = <String>{};
    unawaited(CacheService.remove(_mealSessionCacheKey));
    notifyListeners();
  }

  Future<void> loadCart() async {
    await _restoreMealSessionSelection();
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiClient().get('/customer/cart');
      final data = response.data;
      final cartData = ApiResponse.dataMap(data);
      if (ApiResponse.isSuccess(data) && cartData != null) {
        final cart = CartData.fromJson(cartData);
        _items = cart.items;
        _subtotal = cart.subtotal;
        // Cache the cart data
        await CacheService.put(_cacheKey, cartData, ttl: _cacheTtl);
        _error = null;
      } else {
        _error = ApiResponse.errorMessage(data, 'Unable to load your cart.');
      }
    } catch (_) {
      // On network failure, try to show cached cart
      await _loadFromCache();
      _error =
          'Unable to refresh your cart. Check your connection and try again.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> _restoreMealSessionSelection() async {
    if (_mealSessionUuid != null) return;
    try {
      final cached = await CacheService.get(_mealSessionCacheKey);
      if (cached is! Map) return;
      final value = Map<String, dynamic>.from(cached);
      final uuid = value['uuid']?.toString();
      final date = value['date']?.toString();
      final name = value['name']?.toString();
      if (uuid == null || uuid.isEmpty || date == null || name == null) return;
      _mealSessionUuid = uuid;
      _serviceDate = date;
      _mealSessionName = name;
      _mealSessionProductUuids = (value['product_uuids'] is List)
          ? (value['product_uuids'] as List)
              .map((item) => item.toString())
              .toSet()
          : <String>{};
    } catch (_) {
      await CacheService.remove(_mealSessionCacheKey);
    }
  }

  Future<void> _loadFromCache() async {
    try {
      final cached = await CacheService.get(_cacheKey);
      if (cached is Map && _items.isEmpty) {
        final cart = CartData.fromJson(Map<String, dynamic>.from(cached));
        _items = cart.items;
        _subtotal = cart.subtotal;
      }
    } catch (_) {
      // Cache read failed; ignore
    }
  }

  Future<bool> addItem(
      {required String productUuid,
      String? variantUuid,
      int quantity = 1,
      List<int>? addonIds}) async {
    _error = null;
    if (_mealSessionUuid != null &&
        !_mealSessionProductUuids.contains(productUuid)) {
      _error = 'This item is not available in the selected meal session.';
      notifyListeners();
      return false;
    }
    try {
      final payload = <String, dynamic>{
        'product_uuid': productUuid,
        'quantity': quantity
      };
      if (variantUuid != null) payload['variant_uuid'] = variantUuid;
      if (addonIds != null && addonIds.isNotEmpty)
        payload['addon_ids'] = addonIds;
      final response =
          await ApiClient().post('/customer/cart/items', data: payload);
      final cartData = ApiResponse.dataMap(response.data);
      if (ApiResponse.isSuccess(response.data) && cartData != null) {
        final cart = CartData.fromJson(cartData);
        _items = cart.items;
        _subtotal = cart.subtotal;
        notifyListeners();
        return true;
      }
      _error =
          ApiResponse.errorMessage(response.data, 'Unable to add this item.');
    } catch (_) {
      _error = 'Unable to add this item. Please try again.';
    }
    notifyListeners();
    return false;
  }

  Future<bool> updateItem(int itemId, int quantity) async {
    if (_updatingItemIds.contains(itemId)) return false;
    _updatingItemIds.add(itemId);
    _error = null;
    notifyListeners();
    try {
      final response = await ApiClient()
          .patch('/customer/cart/items/$itemId', data: {'quantity': quantity});
      final cartData = ApiResponse.dataMap(response.data);
      if (ApiResponse.isSuccess(response.data) && cartData != null) {
        final cart = CartData.fromJson(cartData);
        _items = cart.items;
        _subtotal = cart.subtotal;
        return true;
      }
      _error = ApiResponse.errorMessage(
          response.data, 'Unable to update this item.');
    } catch (_) {
      _error = 'Unable to update this item. Please try again.';
    } finally {
      _updatingItemIds.remove(itemId);
      notifyListeners();
    }
    return false;
  }

  Future<bool> removeItem(int itemId) async {
    if (_updatingItemIds.contains(itemId)) return false;
    _updatingItemIds.add(itemId);
    _error = null;
    notifyListeners();
    try {
      final response = await ApiClient().delete('/customer/cart/items/$itemId');
      final cartData = ApiResponse.dataMap(response.data);
      if (ApiResponse.isSuccess(response.data) && cartData != null) {
        final cart = CartData.fromJson(cartData);
        _items = cart.items;
        _subtotal = cart.subtotal;
        return true;
      }
      _error = ApiResponse.errorMessage(
          response.data, 'Unable to remove this item.');
    } catch (_) {
      _error = 'Unable to remove this item. Please try again.';
    } finally {
      _updatingItemIds.remove(itemId);
      notifyListeners();
    }
    return false;
  }

  void reset() {
    _items = [];
    _subtotal = 0;
    _error = null;
    _isLoading = false;
    _updatingItemIds.clear();
    _mealSessionUuid = null;
    _serviceDate = null;
    _mealSessionName = null;
    _mealSessionProductUuids = <String>{};
    CacheService.remove(_cacheKey);
    CacheService.remove(_mealSessionCacheKey);
    notifyListeners();
  }

  Future<bool> clearCart() async {
    try {
      final response = await ApiClient().delete('/customer/cart');
      if (ApiResponse.isSuccess(response.data)) {
        _items = [];
        _subtotal = 0;
        _error = null;
        await CacheService.remove(_cacheKey);
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
