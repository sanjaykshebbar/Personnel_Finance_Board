package com.example.smssync

import android.content.Context
import android.content.SharedPreferences

class SettingsManager(context: Context) {
    private val prefs: SharedPreferences = context.getSharedPreferences("sms_sync_prefs", Context.MODE_PRIVATE)

    fun saveServerUrl(url: String) {
        prefs.edit().putString("server_url", url).apply()
    }

    fun getServerUrl(): String {
        return prefs.getString("server_url", "") ?: ""
    }
}
