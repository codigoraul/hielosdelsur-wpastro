import { readdir, readFile, writeFile } from 'fs/promises';
import { join } from 'path';

async function fixPaths(dir) {
  const files = await readdir(dir, { withFileTypes: true });
  
  for (const file of files) {
    const fullPath = join(dir, file.name);
    
    if (file.isDirectory()) {
      await fixPaths(fullPath);
    } else if (file.name.endsWith('.html')) {
      let content = await readFile(fullPath, 'utf-8');
      
      // Reemplazar rutas absolutas por relativas
      content = content.replace(/href="\/_astro\//g, 'href="./_astro/');
      content = content.replace(/src="\/_astro\//g, 'src="./_astro/');
      content = content.replace(/href="\/favicon\.svg"/g, 'href="./favicon.svg"');
      content = content.replace(/src="\/favicon\.svg"/g, 'src="./favicon.svg"');
      content = content.replace(/href="\/images\//g, 'href="./images/');
      content = content.replace(/src="\/images\//g, 'src="./images/');
      content = content.replace(/href="\/icons\//g, 'href="./icons/');
      content = content.replace(/src="\/icons\//g, 'src="./icons/');
      content = content.replace(/href="\/slider\//g, 'href="./slider/');
      content = content.replace(/src="\/slider\//g, 'src="./slider/');
      content = content.replace(/href="\/sillas\//g, 'href="./sillas/');
      content = content.replace(/src="\/sillas\//g, 'src="./sillas/');
      
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
