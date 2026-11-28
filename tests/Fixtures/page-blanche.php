<?php

// Une page PDF vierge. Les positions de la table xref comptent les fins de ligne CRLF.
return str_replace("\n", "\r\n", <<<'PDF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << >> >>
endobj
xref
0 4
0000000000 65535 f
0000000010 00000 n
0000000062 00000 n
0000000122 00000 n
trailer
<< /Size 4 /Root 1 0 R >>
startxref
213
%%EOF
PDF);
