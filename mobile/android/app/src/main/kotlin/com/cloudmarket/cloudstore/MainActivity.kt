package com.cloudmarket.cloudstore

import android.content.Intent
import android.net.Uri
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "cloudmarket/device_actions")
            .setMethodCallHandler { call, result ->
                val intent = when (call.method) {
                    "call" -> call.argument<String>("phone")?.takeIf { it.isNotBlank() }?.let {
                        Intent(Intent.ACTION_DIAL, Uri.parse("tel:$it"))
                    }
                    "directions" -> call.argument<String>("address")?.takeIf { it.isNotBlank() }?.let {
                        Intent(Intent.ACTION_VIEW, Uri.parse("geo:0,0?q=${Uri.encode(it)}"))
                    }
                    else -> {
                        result.notImplemented()
                        null
                    }
                }

                if (intent == null) {
                    if (call.method == "call" || call.method == "directions") result.success(false)
                    return@setMethodCallHandler
                }
                if (intent.resolveActivity(packageManager) == null) {
                    result.success(false)
                    return@setMethodCallHandler
                }
                startActivity(intent)
                result.success(true)
            }
    }
}
