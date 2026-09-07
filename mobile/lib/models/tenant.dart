class Tenant {
  final int id;
  final String name;
  final String slug;
  final String businessType;
  final String status;

  Tenant({
    required this.id,
    required this.name,
    required this.slug,
    required this.businessType,
    required this.status,
  });

  factory Tenant.fromJson(Map<String, dynamic> json) {
    return Tenant(
      id: json['id'] as int,
      name: json['name'] as String,
      slug: json['slug'] as String,
      businessType: json['business_type'] as String,
      status: json['status'] as String,
    );
  }
}

class TenantBranding {
  final String? primaryColor;
  final String? logoUrl;
  final String? tagline;

  TenantBranding({this.primaryColor, this.logoUrl, this.tagline});

  factory TenantBranding.fromJson(Map<String, dynamic> json) {
    return TenantBranding(
      primaryColor: json['primary_color'] as String?,
      logoUrl: json['logo_url'] as String?,
      tagline: json['tagline'] as String?,
    );
  }
}
