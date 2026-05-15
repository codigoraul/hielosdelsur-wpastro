import { readdir, readFile, writeFile } from 'fs/promises';
import { join, relative } from 'path';

async function fixPaths(dir, baseDir = './dist') {
  const files = await readdir(dir, { withFileTypes: true });
  
  for (const file of files) {
    const fullPath = join(dir, file.name);
    
    if (file.isDirectory()) {
      await fixPaths(fullPath, baseDir);
    } else if (file.name.endsWith('.html')) {
      let content = await readFile(fullPath, 'utf-8');
      
      // Calcular profundidad (cuántos niveles desde dist/)
      const relativePath = relative(baseDir, dir);
      const depth = relativePath === '' ? 0 : relativePath.split('/').length;
      const prefix = depth === 0 ? './' : '../'.repeat(depth);
      
      console.log(`Processing: ${fullPath} (depth: ${depth}, prefix: ${prefix})`);
      
      // Reemplazar TODOS los href que empiezan con / (excepto URLs externas)
      content = content.replace(/href="\/(?!\/|https?:)/g, `href="${prefix}`);
      
      // Reemplazar TODOS los src que empiezan con / (excepto URLs externas)
      content = content.replace(/src="\/(?!\/|https?:)/g, `src="${prefix}`);
      
      await writeFile(fullPath, content, 'utf-8');
      console.log(`✓ Fixed: ${fullPath}`);
    } else if (file.name.endsWith('.css') || file.name.endsWith('.js')) {
      let content = await readFile(fullPath, 'utf-8');
      
      // Para CSS/JS siempre usar rutas relativas simples
      content = content.replace(/url\(["']?\/(?!\/|https?:)/g, 'url("../');
      
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
