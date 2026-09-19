function d(r,a,l){const c=o=>{const e=String(o??"");return e.includes(",")||e.includes('"')||e.includes(`
`)?`"${e.replace(/"/g,'""')}"`:e},t=[a.map(c).join(",")];for(const o of l)t.push(o.map(c).join(","));const i=new Blob([t.join(`
`)],{type:"text/csv;charset=utf-8;"}),s=URL.createObjectURL(i),n=document.createElement("a");n.href=s,n.download=r,n.click(),URL.revokeObjectURL(s)}export{d};
