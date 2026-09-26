package com.example.cantina;

import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.Spinner;
import android.widget.TextView;
import androidx.test.platform.app.InstrumentationRegistry;
import org.junit.Test;
import static org.junit.Assert.*;
import java.util.concurrent.atomic.AtomicReference;

public class FluxoTest {
    private final Instrumentation instrumentation=InstrumentationRegistry.getInstrumentation();
    private void ui(Runnable action){instrumentation.runOnMainSync(action);instrumentation.waitForIdleSync();}
    private Activity launch(Class<? extends Activity> type){
        Activity a=instrumentation.startActivitySync(new Intent(instrumentation.getTargetContext(),type).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));
        instrumentation.waitForIdleSync();return a;
    }
    private Activity clickTo(Activity from,int viewId,Class<? extends Activity> type){
        Instrumentation.ActivityMonitor monitor=instrumentation.addMonitor(type.getName(),null,false);
        ui(()->from.findViewById(viewId).performClick());
        Activity result=monitor.waitForActivityWithTimeout(20000);instrumentation.removeMonitor(monitor);
        assertNotNull("A tela esperada não abriu: "+type.getSimpleName(),result);instrumentation.waitForIdleSync();return result;
    }
    private String value(Activity activity,int id){
        AtomicReference<String> result=new AtomicReference<>();ui(()->result.set(((TextView)activity.findViewById(id)).getText().toString()));return result.get();
    }
    private void awaitText(Activity activity,int id,String expected)throws Exception{
        long limit=System.currentTimeMillis()+20000;
        while(System.currentTimeMillis()<limit){if(value(activity,id).contains(expected))return;Thread.sleep(150);}
        fail("Texto esperado: "+expected+"; recebido: "+value(activity,id));
    }
    @Test public void responsavelVeSomenteFilhoEVaiParaLoginAoSairDoApp()throws Exception {
        Activity main=launch(MainActivity.class);
        Activity login=clickTo(main,R.id.btnResponsavel,CadastroPaisActivity.class);
        ui(()->{
            ((EditText)login.findViewById(R.id.et_cpf)).setText("90909090909");
            ((EditText)login.findViewById(R.id.et_senha)).setText("Teste-responsavel-integracao-2026");
        });
        Activity area=clickTo(login,R.id.bnt_cadastrar,TelaPrincipalPaisActivity.class);
        awaitText(area,R.id.infoFilho,"Joao");
        assertEquals("responsavel",Api.perfil);
        Activity pedidos=clickTo(area,R.id.btnVerExtrato,ExtratoActivity.class);
        awaitText(pedidos,R.id.produtosPedido1,"Sanduíche Natural");
        instrumentation.getUiAutomation().performGlobalAction(android.accessibilityservice.AccessibilityService.GLOBAL_ACTION_HOME);
        long limit=System.currentTimeMillis()+5000;
        while(!Api.perfil.isEmpty()&&System.currentTimeMillis()<limit)Thread.sleep(100);
        assertTrue("Área dos pais deve bloquear ao sair do aplicativo",Api.perfil.isEmpty());
    }
    @Test public void compraUsaBancoReal()throws Exception{
        Activity main=launch(MainActivity.class);
        String cpf="8"+String.valueOf(System.currentTimeMillis()).substring(3);
        ui(()->{
            CarrinhoStore.limpar();
            ((EditText)main.findViewById(R.id.editTextText)).setText("Teste Android integração");
            ((EditText)main.findViewById(R.id.editTextText3)).setText(cpf);
            ((EditText)main.findViewById(R.id.editTextText2)).setText("12");
            ((EditText)main.findViewById(R.id.editTextText4)).setText("teste-android-2026");
        });
        Activity menu=clickTo(main,R.id.bnt_cadastrar,CardapioTelaInicialActivity.class);
        awaitText(menu,R.id.statusCardapio,"Olá");
        Instrumentation.ActivityMonitor productMonitor=instrumentation.addMonitor(ProdutoActivity.class.getName(),null,false);
        ui(()->{
            LinearLayout lista=menu.findViewById(R.id.listaProdutos);
            assertTrue("Cardápio do banco não carregou",lista.getChildCount()>=3);
            assertTrue(((TextView)lista.getChildAt(0)).getText().toString().contains("Sanduíche Natural"));
            lista.getChildAt(0).performClick();
        });
        Activity produto=productMonitor.waitForActivityWithTimeout(10000);instrumentation.removeMonitor(productMonitor);assertNotNull(produto);
        ui(()->{
            produto.findViewById(R.id.btnAumentar).performClick();
            ((EditText)produto.findViewById(R.id.observacaoProduto)).setText("Teste de integração Android");
            produto.findViewById(R.id.btnAdicionarCarrinho).performClick();
        });
        Activity cart=clickTo(menu,R.id.bnt_carinho,CarrinhoActivity.class);
        assertTrue(value(cart,R.id.nomeCarrinho).contains("2x Sanduíche Natural"));
        assertTrue(value(cart,R.id.totalCarrinho).contains("17,00"));
        Activity pagamento=clickTo(cart,R.id.btn_Finalizar,RelatorioPagamentoActivity.class);
        ui(()->{
            ((Spinner)pagamento.findViewById(R.id.spinnerPagamento)).setSelection(1);
            ((Spinner)pagamento.findViewById(R.id.spinnerRecreio)).setSelection(1);
        });
        Activity extrato=clickTo(pagamento,R.id.btnConfirmarPagamento,ExtratoActivity.class);
        awaitText(extrato,R.id.produtosPedido1,"17,00");
        assertTrue(value(extrato,R.id.produtosPedido1).contains("Pendente"));
        assertTrue(value(extrato,R.id.produtosPedido1).contains("15:30"));
        assertTrue(CarrinhoStore.itens().isEmpty());
        ui(()->extrato.finish());
        Activity reopened=launch(ExtratoActivity.class);
        awaitText(reopened,R.id.produtosPedido1,"Teste de integração Android");
        ui(()->reopened.finish());
    }
}
