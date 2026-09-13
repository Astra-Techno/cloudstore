import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

class CacheService {
  static const _prefix = 'cache_';
  static const _ttlPrefix = 'cache_ttl_';

  static Future<void> put(String key, dynamic data,
      {Duration ttl = const Duration(minutes: 30)}) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('$_prefix$key', jsonEncode(data));
    await prefs.setInt(
        '$_ttlPrefix$key', DateTime.now().add(ttl).millisecondsSinceEpoch);
  }

  static Future<dynamic> get(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final ttl = prefs.getInt('$_ttlPrefix$key');
    if (ttl == null || DateTime.now().millisecondsSinceEpoch > ttl) {
      return null; // Expired or not found
    }
    final data = prefs.getString('$_prefix$key');
    return data != null ? jsonDecode(data) : null;
  }

  static Future<void> remove(String key) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('$_prefix$key');
    await prefs.remove('$_ttlPrefix$key');
  }

  static Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    final keys = prefs
        .getKeys()
        .where((k) => k.startsWith(_prefix) || k.startsWith(_ttlPrefix));
    for (final key in keys) {
      await prefs.remove(key);
    }
  }
}
