# 无字典分词
用PHP 实现的电商领域无字典分词。

利于电商商品名称语料，基于统计实现的分词。属于无字典分词。也可以跟现有字典对比做新词发现。

主要有3个参数可以调。

一个是词频，也就是候选词出现的次数 除以 文档长度。

一个是候选词的凝固程度，

假设 AB 不是一个词， A 在文档中出现得概率为 P(A)， B 在文档中出现得概率为 P(B)，
那 AB 恰好合在一起出现的概率就应该近似 P(A)*P(B)， 也就是 P(AB) ≈ P(A)*P(B)。
所以凝固程度的计算公式为 PMI =  P(AB) / (P(A)*P(B))，凝固程度超过某个阈值就可以认为是一个词。
可以想到，凝合程度最高的文本片段就是诸如“蝙蝠”、“蜘蛛”、“彷徨”、“忐忑”、“玫瑰”之类的词了，
这些词里的每一个字几乎总是会和另一个字同时出现，从不在其他场合中使用

一个是候选词的自由程度

代码里用信息熵表示 候选词 左邻字 跟 右邻字的丰富程度，也叫自由程度。丰富程度越高，候选词越能被认为是一个独立的词。
信息熵的定义是这样的，如果一个骰子有6面，分别是 1，1，1，3，3，4。如果甩到 4 ，因为甩到1的概率是1/6，那么得到的信息量就可以定义为 –log(1/6)，
但是只有1/6的概率得到这么多信息量，所以这个骰子的平均信息熵为， 1/6 * (–log(1/6)) + 1/3 * (–log(1/3)) + 1/2 * (–log(1/2))
同理，这样一个文本 "男士加绒牛仔裤加绒流苏加绒流苏加绒"。"加绒" 这个词的左邻字集合为{ 士，裤，苏，苏 }。
如果一个候选词真的是一个独立的词，他的平均信息熵会非常高，在一个文档里面独立的词的左边或右边可以出现任何的词语。
平均信息熵越高也就代表左右组合的丰富程度越高。


![image](https://github.com/lokenetwork/new_word_detect/blob/master/pic/word.png)
