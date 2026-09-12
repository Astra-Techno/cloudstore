import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../../services/api_client.dart';
import '../../config/app_config.dart';

class BootstrapProvider extends ChangeNotifier {
  int? _tenantId;
  String? _tenantName;
  String? _businessType;
  Color? _primaryColor;
  String? _logoUrl;
  int _deliveryChargeFixed = 0;
  double _serviceChargePercent = 0;
  int _minOrderAmount = 0;
  double _taxRate = 0;
  bool _deliveryEnabled = true;
  bool _pickupEnabled = true;
  List<String> _paymentMethods = const ['cod'];
  Map<String, bool> _capabilities = {};
  bool _isLoaded = false;
  String? _error;

  int? get tenantId => _tenantId;
  String? get tenantName => _tenantName;
  String? get businessType => _businessType;
  Color? get primaryColor => _primaryColor;
  String? get logoUrl => _logoUrl;
  int get deliveryChargeFixed => _deliveryChargeFixed;
  double get serviceChargePercent => _serviceChargePercent;
  int get minOrderAmount => _minOrderAmount;
  double get taxRate => _taxRate;
  bool get deliveryEnabled => _deliveryEnabled;
  bool get pickupEnabled => _pickupEnabled;
  List<String> get paymentMethods => List.unmodifiable(_paymentMethods);
  Map<String, bool> get capabilities => _capabilities;
  bool get isLoaded => _isLoaded;
  String? get error => _error;

  bool hasCapability(String capability) {
    return _capabilities[capability] ?? false;
  }

  Future<void> loadTenant({bool force = false}) async {
    if (_isLoaded && !force) return;

    _error = null;
    _isLoaded = false;
    notifyListeners();

    final api = ApiClient();
    api.setAppToken(AppConfig.appToken);

    try {
      final response = await api.post('/app/bootstrap', data: {
        'app_token': AppConfig.appToken,
      });

      final data = response.data;
      if (data['success'] == true && data['data'] != null) {
        setTenantData(data['data']);
      } else {
        final error = data['error'] is Map
            ? Map<String, dynamic>.from(data['error'] as Map)
            : const <String, dynamic>{};
        final code = error['code']?.toString();
        _error = switch (code) {
          'UNAUTHORIZED' =>
            'This store app is not available right now. Please contact the store.',
          'TENANT_ERROR' =>
            'This store is temporarily unavailable. Please try again shortly.',
          _ => error['message']?.toString() ??
              'Unable to open this store right now.',
        };
        notifyListeners();
      }
    } on DioException catch (error) {
      final responseData = error.response?.data;
      final apiError = responseData is Map && responseData['error'] is Map
          ? Map<String, dynamic>.from(responseData['error'] as Map)
          : const <String, dynamic>{};
      final code = apiError['code']?.toString();
      _error = switch (code) {
        'UNAUTHORIZED' =>
          'This app build is no longer authorised. Please install the latest app from the store.',
        'TENANT_ERROR' =>
          'This store is temporarily unavailable. Please try again shortly.',
        _ when error.type == DioExceptionType.connectionTimeout ||
                error.type == DioExceptionType.connectionError =>
          'Unable to reach the store server. Check your internet connection and try again.',
        _ => apiError['message']?.toString() ??
            'Unable to open this store right now. Please try again.',
      };
      _isLoaded = false;
      notifyListeners();
    } catch (_) {
      _error = 'Unable to open this store right now. Please try again.';
      _isLoaded = false;
      notifyListeners();
    }
  }

  void setTenantData(Map<String, dynamic> data) {
    _tenantId = data['tenant']?['id'] as int?;
    _tenantName = data['tenant']?['name'] as String?;
    _businessType = data['tenant']?['business_type'] as String?;

    final brandingRaw = data['branding'];
    final branding =
        brandingRaw is Map ? Map<String, dynamic>.from(brandingRaw) : null;
    if (branding != null) {
      if (branding['primary_color'] != null) {
        final hex = branding['primary_color'].toString().replaceFirst('#', '');
        if (hex.length == 6) {
          final colorValue = int.tryParse('FF$hex', radix: 16);
          if (colorValue != null) {
            _primaryColor = Color(colorValue);
          }
        }
      }
      _logoUrl = branding['logo_url'] as String?;
    }

    final chargesRaw = data['charges'];
    if (chargesRaw is Map) {
      _deliveryChargeFixed = (chargesRaw['delivery_charge_fixed'] is int)
          ? chargesRaw['delivery_charge_fixed'] as int
          : int.tryParse(
                  chargesRaw['delivery_charge_fixed']?.toString() ?? '') ??
              0;
      _serviceChargePercent = (chargesRaw['service_charge_percent'] is num)
          ? (chargesRaw['service_charge_percent'] as num).toDouble()
          : double.tryParse(
                  chargesRaw['service_charge_percent']?.toString() ?? '') ??
              0;
      _minOrderAmount = (chargesRaw['min_order_amount'] is int)
          ? chargesRaw['min_order_amount'] as int
          : int.tryParse(chargesRaw['min_order_amount']?.toString() ?? '') ?? 0;
      _taxRate = (chargesRaw['tax_rate'] is num)
          ? (chargesRaw['tax_rate'] as num).toDouble()
          : double.tryParse(chargesRaw['tax_rate']?.toString() ?? '') ?? 0;
    }

    final fulfilment = data['fulfilment'];
    if (fulfilment is Map) {
      _deliveryEnabled = fulfilment['delivery_enabled'] != false;
      _pickupEnabled = fulfilment['pickup_enabled'] != false;
    }
    final payments = data['payment_methods'];
    if (payments is List) {
      _paymentMethods = payments.map((method) => method.toString()).toList();
    }

    final featuresRaw = data['features'];
    final features = featuresRaw is Map
        ? Map<String, dynamic>.from(featuresRaw)
        : <String, dynamic>{};
    _capabilities = features.map((k, v) => MapEntry(k.toString(), v == true));

    _error = null;
    _isLoaded = true;
    notifyListeners();
  }
}
