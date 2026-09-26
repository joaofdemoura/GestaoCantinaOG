package com.example.cantina;

import android.app.Activity;
import android.content.Intent;
import android.os.Handler;
import android.os.Looper;
import org.json.JSONObject;
import java.io.InputStream;
import java.io.ByteArrayOutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;

public final class Api {
    private static final ExecutorService WORKER=Executors.newSingleThreadExecutor();
    private static final Handler MAIN=new Handler(Looper.getMainLooper());
    private static String token="";
    public static String perfil="", alunoId="", nome="";
    public interface Callback { void done(JSONObject data,String error); }
    public static void session(JSONObject data) {
        token=data.optString("token"); perfil=data.optString("perfil");
        alunoId=data.optString("alunoId"); nome=data.optString("nome");
    }
    public static void clear() {token="";perfil="";alunoId="";nome="";}
    static String baseUrl() {
        // Android Studio emulators expose the computer's loopback through 10.0.2.2.
        // Real USB devices continue to use adb reverse. Custom server URLs stay unchanged.
        boolean emulator = "ranchu".equals(android.os.Build.HARDWARE)
                || "goldfish".equals(android.os.Build.HARDWARE);
        if (emulator && "http://127.0.0.1:8080/api".equals(BuildConfig.API_BASE_URL)) {
            return "http://10.0.2.2:8080/api";
        }
        return BuildConfig.API_BASE_URL;
    }
    public static void request(Activity activity,String method,String path,JSONObject body,Callback callback) {
        final String currentToken=token;
        WORKER.execute(()->{
            JSONObject result=null; String error=null; int status=0; HttpURLConnection connection=null;
            try {
                connection=(HttpURLConnection)new URL(baseUrl()+path).openConnection();
                connection.setRequestMethod(method); connection.setConnectTimeout(10000); connection.setReadTimeout(15000);
                connection.setInstanceFollowRedirects(false); connection.setRequestProperty("Accept","application/json");
                if(!currentToken.isEmpty()) connection.setRequestProperty("Authorization","Bearer "+currentToken);
                if(body!=null) {
                    connection.setDoOutput(true); connection.setRequestProperty("Content-Type","application/json; charset=utf-8");
                    try(java.io.OutputStream stream=connection.getOutputStream()) {stream.write(body.toString().getBytes(StandardCharsets.UTF_8));}
                }
                status=connection.getResponseCode();
                try(InputStream stream=status<400?connection.getInputStream():connection.getErrorStream()) {
                    if(stream==null)throw new java.io.IOException();
                    ByteArrayOutputStream bytes=new ByteArrayOutputStream(); byte[] buffer=new byte[4096]; int count;
                    while((count=stream.read(buffer))!=-1) {bytes.write(buffer,0,count);if(bytes.size()>2000000)throw new java.io.IOException();}
                    result=new JSONObject(new String(bytes.toByteArray(),StandardCharsets.UTF_8));
                }
                if(status<200||status>=300)error=result.optString("error","Falha ao acessar o servidor.");
            } catch(Exception e) {error="Não foi possível conectar à cantina. Verifique se o servidor local está ligado e tente novamente.";}
            finally {if(connection!=null)connection.disconnect();}
            final JSONObject response=result; final String message=error; final int code=status;
            MAIN.post(()->{
                if(activity.isFinishing()||activity.isDestroyed())return;
                if(code==401&&!path.endsWith("login")) {
                    clear();activity.startActivity(new Intent(activity,TelaInicialActivity.class).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP));activity.finish();
                }
                callback.done(response,message);
            });
        });
    }
    private Api() {}
}
