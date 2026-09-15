import 'package:flutter/material.dart';
import '../../services/api_client.dart';

class FavouritesProvider extends ChangeNotifier {
  List<String> _favouriteProductUuids = [];
  bool _isLoading = false;

  List<String> get favouriteProductUuids =>
      List.unmodifiable(_favouriteProductUuids);
  bool get isLoading => _isLoading;

  bool isFavourite(String uuid) => _favouriteProductUuids.contains(uuid);

  Future<void> loadFavourites() async {
    _isLoading = true;
    notifyListeners();
    try {
      final response = await ApiClient().get('/customer/favourites');
      final data = response.data;
      if (data['success'] == true && data['data'] is List) {
        _favouriteProductUuids = (data['data'] as List)
            .map((item) => (item['product_uuid'] ?? item['uuid'] ?? '')
                .toString())
            .where((uuid) => uuid.isNotEmpty)
            .toList();
      }
    } catch (_) {
      // Favourites are supplementary; silently fail.
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void reset() {
    _favouriteProductUuids = [];
    _isLoading = false;
    notifyListeners();
  }

  Future<void> toggleFavourite(String productUuid) async {
    // Optimistic update
    final wasFavourite = isFavourite(productUuid);
    if (wasFavourite) {
      _favouriteProductUuids.remove(productUuid);
    } else {
      _favouriteProductUuids.add(productUuid);
    }
    notifyListeners();

    try {
      await ApiClient().post('/customer/favourites/$productUuid');
    } catch (_) {
      // Revert on failure
      if (wasFavourite) {
        _favouriteProductUuids.add(productUuid);
      } else {
        _favouriteProductUuids.remove(productUuid);
      }
      notifyListeners();
    }
  }
}
