import { cn } from '@/lib/utils';

/**
 * Text written by admins, shown safely: no HTML, only three simple rules.
 * A blank line starts a new paragraph, "## " starts a heading, "- " a list
 * item.
 */
export function RichText({ text, className }: { text: string | null; className?: string }) {
    if (!text) return null;
    const blocks = text
        .replace(/\r\n/g, '\n')
        .trim()
        .split(/\n\s*\n/);

    return (
        <div className={cn('space-y-4 leading-relaxed', className)}>
            {blocks.map((block, i) => {
                const lines = block.split('\n');
                const out: React.ReactNode[] = [];
                let list: string[] = [];
                const flush = (key: string) => {
                    if (list.length) {
                        out.push(
                            <ul key={key} className="list-disc space-y-1 pl-5">
                                {list.map((item, j) => (
                                    <li key={j}>{item}</li>
                                ))}
                            </ul>,
                        );
                        list = [];
                    }
                };
                const para: string[] = [];
                lines.forEach((line, j) => {
                    if (line.startsWith('## ')) {
                        flush(`l${j}`);
                        if (para.length) out.push(<p key={`p${j}`}>{para.splice(0).join(' ')}</p>);
                        out.push(
                            <h2 key={`h${j}`} className="pt-2 text-xl font-semibold">
                                {line.slice(3)}
                            </h2>,
                        );
                    } else if (line.startsWith('- ')) {
                        if (para.length) out.push(<p key={`p${j}`}>{para.splice(0).join(' ')}</p>);
                        list.push(line.slice(2));
                    } else {
                        flush(`l${j}`);
                        para.push(line);
                    }
                });
                flush('end');
                if (para.length) out.push(<p key="last">{para.join(' ')}</p>);
                return <div key={i}>{out}</div>;
            })}
        </div>
    );
}
