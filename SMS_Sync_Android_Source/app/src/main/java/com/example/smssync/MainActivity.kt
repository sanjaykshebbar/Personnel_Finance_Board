package com.example.smssync

import android.Manifest
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.core.app.ActivityCompat
import com.example.smssync.ui.theme.SMS_SyncTheme
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Request Permissions
        ActivityCompat.requestPermissions(this, 
            arrayOf(Manifest.permission.RECEIVE_SMS, Manifest.permission.READ_SMS), 
            101)

        setContent {
            SMS_SyncTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    SMS_SyncScreen()
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SMS_SyncScreen() {
    val context = androidx.compose.ui.platform.LocalContext.current
    val scope = rememberCoroutineScope()
    val settings = remember { SettingsManager(context) }
    
    var serverUrl by remember { mutableStateOf(settings.getServerUrl()) }
    var syncStatus by remember { mutableStateOf("Ready") }

    Column(modifier = Modifier.padding(24.dp).fillMaxWidth()) {
        Text("Quick SMS Sync", style = MaterialTheme.typography.headlineLarge)
        Spacer(modifier = Modifier.height(32.dp))
        
        OutlinedTextField(
            value = serverUrl,
            onValueChange = { 
                serverUrl = it
                settings.saveServerUrl(it)
            },
            label = { Text("Server API URL") },
            modifier = Modifier.fillMaxWidth(),
            placeholder = { Text("http://192.168.1.5:8080/api/ingest_messages.php") }
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Text("Status: $syncStatus", style = MaterialTheme.typography.bodyMedium)
        
        Spacer(modifier = Modifier.height(32.dp))
        
        Button(
            onClick = {
                scope.launch {
                    syncStatus = "Syncing..."
                    val success = SmsSyncService.syncLastMessages(context)
                    syncStatus = if (success) "Sync Complete! ✅" else "Sync Failed ❌"
                }
            },
            modifier = Modifier.fillMaxWidth()
        ) {
            Text("Push Last 50 Messages")
        }
        
        Spacer(modifier = Modifier.weight(1f))
        
        Text("Your server should be accessible from your mobile's network.", 
             style = MaterialTheme.typography.labelSmall, 
             color = MaterialTheme.colorScheme.secondary)
    }
}
