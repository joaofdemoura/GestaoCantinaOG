package com.example.cantina;
import android.app.Activity;
import android.app.Instrumentation;
import android.content.Intent;
import android.widget.EditText;
import androidx.test.platform.app.InstrumentationRegistry;
import org.junit.Test;
import static org.junit.Assert.*;
public class VoltarTurmaTest {
 private final Instrumentation inst=InstrumentationRegistry.getInstrumentation();
 private void ui(Runnable r){inst.runOnMainSync(r);inst.waitForIdleSync();}
 private Activity launch(){Activity a=inst.startActivitySync(new Intent(inst.getTargetContext(),TelaInicialActivity.class).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK|Intent.FLAG_ACTIVITY_CLEAR_TASK));inst.waitForIdleSync();return a;}
 private Activity click(Activity a,int id,Class<? extends Activity> type){Instrumentation.ActivityMonitor m=inst.addMonitor(type.getName(),null,false);ui(()->a.findViewById(id).performClick());Activity b=m.waitForActivityWithTimeout(15000);inst.removeMonitor(m);assertNotNull(b);inst.waitForIdleSync();return b;}
 @Test public void voltarRetornaAoInicioEValidaTurma(){
  Activity inicio=launch();assertNotNull(inicio.findViewById(R.id.btnVoltar));
  Activity aluno=click(inicio,R.id.bnt_aluno,MainActivity.class);
  ui(()->{((EditText)aluno.findViewById(R.id.editTextText3)).setText("11111111111");((EditText)aluno.findViewById(R.id.editTextText4)).setText("teste123");aluno.findViewById(R.id.bnt_cadastrar).performClick();assertNotNull(((EditText)aluno.findViewById(R.id.editTextTurma)).getError());});
  ui(()->aluno.findViewById(R.id.btnVoltar).performClick());assertTrue(aluno.isFinishing());
  Activity pais=click(inicio,R.id.bnt_pai,CadastroPaisActivity.class);
  ui(()->pais.findViewById(R.id.btnVoltar).performClick());assertTrue(pais.isFinishing());
  ui(inicio::finish);
 }
}
