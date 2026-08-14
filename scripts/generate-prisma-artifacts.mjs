import fs from 'node:fs';
import path from 'node:path';

const source = process.argv[2];

if (!source) {
throw new Error('Usage: node scripts/generate-prisma-artifacts.mjs <schema.prisma>');
}

const root = path.resolve(import.meta.dirname, '..');
const schema = fs.readFileSync(source, 'utf8');
const enumNames = new Set([...schema.matchAll(/^enum\s+(\w+)\s*\{/gm)].map((match) => match[1]));
const modelBlocks = [...schema.matchAll(/^model\s+(\w+)\s*\{([\s\S]*?)^\}/gm)];
const modelNames = new Set(modelBlocks.map((match) => match[1]));

const quote = (value) => value.replaceAll('\\', '\\\\').replaceAll("'", "\\'");
const tableLines = [];

function columnExpression(name, type, attributes, optional) {
  const isId = /@id\b/.test(attributes);
  const autoIncrement = /@default\(autoincrement\(\)\)/.test(attributes);
  let expression;
  let supportsDefault = true;

  if (type === 'Int' && isId && autoIncrement) {
expression = `$table->id('${name}')`;
} else if (type === 'Int') {
expression = `$table->integer('${name}')`;
} else if (type === 'BigInt') {
expression = `$table->bigInteger('${name}')`;
} else if (type === 'Float') {
expression = `$table->double('${name}')`;
} else if (type === 'Decimal') {
expression = `$table->decimal('${name}', 20, 4)`;
} else if (type === 'Boolean') {
expression = `$table->boolean('${name}')`;
} else if (type === 'DateTime') {
expression = `$table->dateTime('${name}')`;
} else if (type === 'Json') {
 expression = `$table->json('${name}')`; supportsDefault = false;
} else if (type === 'Bytes') {
 expression = `$table->binary('${name}')`; supportsDefault = false;
} else if (type === 'String' && /@db\.Text\b/.test(attributes)) {
 expression = `$table->longText('${name}')`; supportsDefault = false;
} else if (type === 'String' && /@db\.LongText\b/.test(attributes)) {
 expression = `$table->longText('${name}')`; supportsDefault = false;
} else {
    const varchar = attributes.match(/@db\.VarChar\((\d+)\)/);
    expression = varchar ? `$table->string('${name}', ${varchar[1]})` : `$table->string('${name}')`;
  }

  if (optional) {
expression += '->nullable()';
}

  if (isId && !(type === 'Int' && autoIncrement)) {
expression += '->primary()';
}

  if (/@unique\b/.test(attributes)) {
expression += '->unique()';
}

  if (/@updatedAt\b/.test(attributes)) {
expression += '->useCurrent()->useCurrentOnUpdate()';
} else if (/@default\(now\(\)\)/.test(attributes)) {
expression += '->useCurrent()';
} else {
    const defaultMatch = attributes.match(/@default\((true|false|-?\d+(?:\.\d+)?|"(?:[^"\\]|\\.)*")\)/);

    if (defaultMatch && supportsDefault) {
      const raw = defaultMatch[1];
      const value = raw.startsWith('"') ? `'${quote(JSON.parse(raw))}'` : raw;
      expression += `->default(${value})`;
    }
  }

  return `${expression};`;
}

for (const [, modelName, body] of modelBlocks) {
  const mapped = body.match(/@@map\("([^"]+)"\)/)?.[1] ?? modelName;
  const lines = body.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
  tableLines.push(`        Schema::create('${quote(mapped)}', function (Blueprint $table) {`);

  for (const line of lines) {
    if (line.startsWith('//') || line.startsWith('@@')) {
continue;
}

    const field = line.match(/^(\w+)\s+([\w]+)(\? |\[\] |\?|\[\])?(.*)$/);

    if (!field) {
continue;
}

    const [, name, baseType, modifier = '', attributes = ''] = field;

    if (modelNames.has(baseType)) {
continue;
}

    const optional = modifier.trim() === '?';
    tableLines.push(`            ${columnExpression(name, enumNames.has(baseType) ? 'String' : baseType, attributes, optional)}`);
  }

  for (const line of lines) {
    let match = line.match(/^@@unique\(\[([^\]]+)\]/);

    if (match) {
tableLines.push(`            $table->unique([${match[1].split(',').map((item) => `'${item.trim()}'`).join(', ')}]);`);
}

    match = line.match(/^@@index\(\[([^\]]+)\]/);

    if (match) {
tableLines.push(`            $table->index([${match[1].split(',').map((item) => `'${item.trim()}'`).join(', ')}]);`);
}

    match = line.match(/^@@id\(\[([^\]]+)\]/);

    if (match) {
tableLines.push(`            $table->primary([${match[1].split(',').map((item) => `'${item.trim()}'`).join(', ')}]);`);
}
  }

  tableLines.push('        });', '');

  if (modelName === 'User') {
continue;
}

  const fields = lines
    .map((line) => line.match(/^(\w+)\s+([\w]+)(\? |\[\] |\?|\[\])?/))
    .filter(Boolean)
    .filter((match) => !modelNames.has(match[2]) && match[3]?.trim() !== '[]');
  const casts = fields.flatMap((match) => {
    const [, name, type] = match;

    if (type === 'Boolean') {
return [`        '${name}' => 'boolean',`];
}

    if (type === 'Int' || type === 'BigInt') {
return [`        '${name}' => 'integer',`];
}

    if (type === 'Float' || type === 'Decimal') {
return [`        '${name}' => 'decimal:4',`];
}

    if (type === 'DateTime') {
return [`        '${name}' => 'datetime',`];
}

    if (type === 'Json') {
return [`        '${name}' => 'array',`];
}

    return [];
  });
  const model = `<?php\n\nnamespace App\\Models\\Domain;\n\nuse Illuminate\\Database\\Eloquent\\Model;\n\nclass ${modelName} extends Model\n{\n    protected $table = '${quote(mapped)}';\n\n    protected $guarded = [];\n\n    public $timestamps = false;\n\n    protected function casts(): array\n    {\n        return [\n${casts.join('\n')}\n        ];\n    }\n}\n`;
  const modelDir = path.join(root, 'app', 'Models', 'Domain');
  fs.mkdirSync(modelDir, { recursive: true });
  fs.writeFileSync(path.join(modelDir, `${modelName}.php`), model);
}

const drops = modelBlocks.slice().reverse().map(([, modelName, body]) => {
  const mapped = body.match(/@@map\("([^"]+)"\)/)?.[1] ?? modelName;

  return `        Schema::dropIfExists('${quote(mapped)}');`;
});

const migration = `<?php\n\nuse Illuminate\\Database\\Migrations\\Migration;\nuse Illuminate\\Database\\Schema\\Blueprint;\nuse Illuminate\\Support\\Facades\\Schema;\n\nreturn new class extends Migration\n{\n    public function up(): void\n    {\n${tableLines.join('\n')}    }\n\n    public function down(): void\n    {\n${drops.join('\n')}\n    }\n};\n`;

const migrationPath = path.join(root, 'database', 'migrations', '2026_08_12_000000_create_decomkt_domain_tables.php');
fs.writeFileSync(migrationPath, migration);
console.log(`Generated ${modelBlocks.length} tables and ${modelBlocks.length - 1} domain models.`);
