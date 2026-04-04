package com.example.smssync

import android.content.Context
import android.provider.Telephony
import android.util.Log
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException

object SmsSyncService {
    private val client = OkHttpClient()
    private val JSON = "application/json; charset=utf-8".toMediaType()

    suspend fun syncSingleMessage(context: Context, sender: String, body: String, timestamp: Long): Boolean {
        val settings = SettingsManager(context)
        val url = settings.getServerUrl()
        if (url.isEmpty()) return false

        val json = JSONObject().apply {
            put("sender", sender)
            put("message_text", body)
            put("timestamp", timestamp)
        }

        return postData(url, json.toString())
    }

    suspend fun syncLastMessages(context: Context): Boolean {
        val settings = SettingsManager(context)
        val url = settings.getServerUrl()
        if (url.isEmpty()) return false

        val messages = JSONArray()
        val cursor = context.contentResolver.query(
            Telephony.Sms.CONTENT_URI,
            arrayOf("address", "body", "date"),
            null, null, "date DESC LIMIT 50"
        )

        cursor?.use {
            while (it.moveToNext()) {
                val json = JSONObject().apply {
                    put("sender", it.getString(0))
                    put("message_text", it.getString(1))
                    put("timestamp", it.getLong(2))
                }
                messages.put(json)
            }
        }

        return postData(url, messages.toString())
    }

    private fun postData(url: String, json: String): Boolean {
        val body = json.toRequestBody(JSON)
        val request = Request.Builder()
            .url(url)
            .post(body)
            .build()

        return try {
            client.newCall(request).execute().use { response ->
                if (!response.isSuccessful) Log.e("SMS_SYNC", "Failed: ${response.code}")
                response.isSuccessful
            }
        } catch (e: Exception) {
            Log.e("SMS_SYNC", "Error: ${e.message}")
            false
        }
    }
}
