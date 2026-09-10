import 'package:flutter/material.dart';
import '../../services/api_client.dart';
import '../../config/app_config.dart';

class BootstrapProvider extends ChangeNotifier {
  int? _tenantId;
  String? _tenantName;
  String? _businessType;
  Color? _primaryColor;
  Map<String, bool> _capabilities = {};
  bool _isLoaded = false;
  String? _error;

  int? get tenantId => _tenantId;
  String? get tenantName => _tenantName;
  String? get businessType => _businessType;
  Color? get primaryColor => _primaryColor;
  Map<String, bool> get capabilities => _capabilities;
  bool get isLoaded => _isLoaded;
  String? get error => _error;

  bool hasCapability(String capability) {
    return _capabilities[capability] ?? false;
  }

  Future<void> loadTenant() async {
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
        _error = data['error']?['message'] ?? 'Bootstrap failed';
        notifyListeners();
      }
    } catch (e) {
      _error = 'Failed to connect to server';
      _isLoaded = true;
      notifyListeners();
    }
  }

  void setTenantData(Map<String, dynamic> data) {
    _tenantId = data['tenant']?['id'] as int?;
    _tenantName = data['tenant']?['name'] as String?;
    _businessType = data['tenant']?['business_type'] as String?;

    final brandingRaw = data['branding'];
    final branding = brandingRaw is Map ? Map<String, dynamic>.from(brandingRaw) : null;
    if (branding != null && branding['primary_color'] != null) {
      final hex = branding['primary_color'].toString().replaceFirst('#', '');
      if (hex.length == 6) {
        _primaryColor = Color(int.parse('FF$hex', radix: 16));
      }
    }

    final featuresRaw = data['features'];
    final features = featuresRaw is Map ? Map<String, dynamic>.from(featuresRaw) : <String, dynamic>{};
    _capabilities = features.map((k, v) => MapEntry(k.toString(), v == true));

    _error = null;
    _isLoaded = true;
    notifyListeners();
  }
}
