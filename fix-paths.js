import { readdir, readFile, writeFile } from 'fs/promises';
import { join } from 'path';

async function fixPaths(dir) {
  const files = await readdir(dir, { withFileTypes: true });
  
  for (const file of files) {
    const fullPath = join(dir, file.name);
    
    if (file.isDirectory()) {
      await fixPaths(fullPath);
    } else if (file.name.endsWith('.html') || file.name.endsWith('.css') || file.name.endsWith('.js')) {
      let content = await readFile(fullPath, 'utf-8');
      
      // Reemplazar TODOS los href que empiezan con / (excepto URLs externas)
      // Esto captura href="/algo" y lo convierte en href="./algo"
      content = content.replace(/href="\/(?!\/|https?:)/g, 'href="./');
      
      // Reemplazar TODOS los src que empiezan con / (excepto URLs externas)
      content = content.replace(/src="\/(?!\/|https?:)/g, 'src="./');
      
      // Reemplazar url() en CSS/JS
      content = content.replace(/url\(["']?\/(?!\/|https?:)/g, 'url("./');
      
      await writeFile(fullPath, content, 'utf-8');
      console.log(`✓ Fixed: ${fullPath}`);
    }
  }
}

fixPaths('./dist').then(() => {
  console.log('✅ All paths fixed!');
}).catch(err => {
  console.error('❌ Error fixing paths:', err);
  process.exit(1);
});
