package com.example.cantina;

import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import androidx.test.platform.app.InstrumentationRegistry;
import org.json.JSONObject;
import org.junit.Test;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;
import static org.junit.Assert.*;

public class ConexaoServidorTest {
    private final Instrumentation instrumentation=InstrumentationRegistry.getInstrumentation();
    private JSONObject request(Activity activity,String method,String path,JSONObject body)throws Exception {
        CountDownLatch done=new CountDownLatch(1);
        AtomicReference<JSONObject> response=new AtomicReference<>();
        AtomicReference<String> error=new AtomicReference<>();
        instrumentation.runOnMainSync(()->Api.request(activity,method,path,body,(data,message)->{
            response.set(data);error.set(message);done.countDown();
        }));
        assertTrue("A API não respondeu no prazo",done.await(30,TimeUnit.SECONDS));
        assertNull(error.get(),error.get());return response.get();
    }
    @Test public void emuladorConsultaBancoSemAdbReverse()throws Exception {
        Activity main=instrumentation.startActivitySync(new Intent(instrumentation.getTargetContext(),MainActivity.class)
                .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
        instrumentation.waitForIdleSync();
        assertEquals("http://10.0.2.2:8000/api/android",Api.baseUrl());
        assertEquals("php",request(main,"GET","/health",null).getString("backend"));
        JSONObject session=request(main,"POST","/login",new JSONObject().put("cpf","11111111111").put("senha","1234"));
        instrumentation.runOnMainSync(()->Api.session(session));
        try {
            JSONObject menu=request(main,"GET","/cardapio",null);
            assertTrue(menu.getJSONArray("produtos").length()>=3);
            assertTrue(menu.getJSONArray("produtos").getJSONObject(0).getInt("precoCentavos")>0);
        } finally {
            request(main,"POST","/logout",null);
            instrumentation.runOnMainSync(Api::clear);
        }
    }
}
