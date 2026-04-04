package com.example.smssync

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.util.Log
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class SmsReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Telephony.Sms.Intents.SMS_RECEIVED_ACTION) {
            val messages = Telephony.Sms.Intents.getMessagesFromIntent(intent)
            for (sms in messages) {
                val sender = sms.displayOriginatingAddress
                val body = sms.displayMessageBody
                val timestamp = System.currentTimeMillis()

                Log.d("SMS_SYNC", "Received from $sender: $body")
                
                // Trigger Background Sync
                CoroutineScope(Dispatchers.IO).launch {
                    SmsSyncService.syncSingleMessage(context, sender, body, timestamp)
                }
            }
        }
    }
}
