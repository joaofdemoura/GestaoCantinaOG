package com.example.cantina;
import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import android.widget.EditText;
import android.widget.TextView;
import androidx.test.platform.app.InstrumentationRegistry;
import org.junit.Test;
import static org.junit.Assert.*;

public class NovasTelasTest {
 private final Instrumentation inst=InstrumentationRegistry.getInstrumentation();
 private void ui(Runnable action){inst.runOnMainSync(action);inst.waitForIdleSync();}
 private Activity launch(Class<? extends Activity> type){
  Activity a=inst.startActivitySync(new Intent(inst.getTargetContext(),type).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK|Intent.FLAG_ACTIVITY_CLEAR_TASK));inst.waitForIdleSync();return a;
 }
 private Activity click(Activity a,int id,Class<? extends Activity> type){
  Instrumentation.ActivityMonitor monitor=inst.addMonitor(type.getName(),null,false);
  ui(()->a.findViewById(id).performClick());Activity result=monitor.waitForActivityWithTimeout(20000);inst.removeMonitor(monitor);assertNotNull(result);inst.waitForIdleSync();return result;
 }
 @Test public void escolhaDePerfilAbreLoginCorreto(){
  Activity inicio=launch(TelaInicialActivity.class);
  Activity aluno=click(inicio,R.id.bnt_aluno,MainActivity.class);assertNotNull(aluno.findViewById(R.id.btnEntrar));
  inicio=launch(TelaInicialActivity.class);Activity pai=click(inicio,R.id.bnt_pai,CadastroPaisActivity.class);assertNotNull(pai.findViewById(R.id.et_cpf));
  ui(pai::finish);
 }
 @Test public void painelProtegidoSemSessao(){
  ui(Api::clear);
  Instrumentation.ActivityMonitor monitor=inst.addMonitor(TelaInicialActivity.class.getName(),null,false);
  launch(TelaPrincipalPaisActivity.class);
  Activity inicio=monitor.waitForActivityWithTimeout(10000);inst.removeMonitor(monitor);assertNotNull(inicio);ui(inicio::finish);
 }
 @Test public void painelPaisCarregaSaldoEAbreFormularios()throws Exception{
  Activity inicio=launch(TelaInicialActivity.class);
  Activity login=click(inicio,R.id.bnt_pai,CadastroPaisActivity.class);
  ui(()->{((EditText)login.findViewById(R.id.et_cpf)).setText("90909090909");((EditText)login.findViewById(R.id.et_senha)).setText("Teste-responsavel-integracao-2026");});
  Activity painel=click(login,R.id.bnt_cadastrar,TelaPrincipalPaisActivity.class);
  long deadline=System.currentTimeMillis()+15000;
  java.util.concurrent.atomic.AtomicReference<String> saldo=new java.util.concurrent.atomic.AtomicReference<>("");
  while(System.currentTimeMillis()<deadline){ui(()->saldo.set(((TextView)painel.findViewById(R.id.saldoAtual)).getText().toString()));if(saldo.get().contains("R$"))break;Thread.sleep(100);}
  assertTrue(saldo.get(),saldo.get().contains("R$"));
  ui(()->painel.findViewById(R.id.btnMudarSaldo).performClick());inst.sendKeyDownUpSync(android.view.KeyEvent.KEYCODE_BACK);inst.waitForIdleSync();
  ui(()->painel.findViewById(R.id.btnLimiteGasto).performClick());inst.sendKeyDownUpSync(android.view.KeyEvent.KEYCODE_BACK);inst.waitForIdleSync();
  Activity extrato=click(painel,R.id.btnVerExtrato,ExtratoActivity.class);ui(extrato::finish);
  click(painel,R.id.btnSairResponsavel,TelaInicialActivity.class);assertTrue(Api.perfil.isEmpty());
 }
}
