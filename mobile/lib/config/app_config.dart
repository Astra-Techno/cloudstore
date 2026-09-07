class AppConfig {
  static const String appToken = String.fromEnvironment(
    'APP_TOKEN',
    defaultValue: '',
  );

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api/v1',
  );

  static const String appMode = String.fromEnvironment(
    'APP_MODE',
    defaultValue: 'customer', // 'customer' or 'driver'
  );
}
